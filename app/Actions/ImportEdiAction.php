<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\EdiImported;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Exceptions\UnknownSectionException;
use App\Models\VkvpaData;
use App\Services\Edi\CategoryResolver;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiQso;
use App\Services\Scoring\ScoringService;
use App\Support\ContestCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestruje celý tok importu EDI deníku: validace business pravidel,
 * uložení do DB, scoring a vytvoření rezervovaného řádku ve vkvpa_data.
 *
 * Vyhodí výjimku při jakémkoli selhání; úspěch vrací nový VkvpaData řádek.
 *
 * @throws TDateNotContestDayException TDate neodpovídá termínu kola (3. neděle v měsíci)
 * @throws RoundNotFoundException Pro datum z TDate neexistuje žádné kolo
 * @throws TDateMismatchException TDate v hlavičce neodpovídá datům QSO
 * @throws DuplicateEdiException Deník pro tuto stanici a kolo již existuje
 * @throws UnknownBandException Pásmo z hlavičky EDI nelze přiřadit kategorii
 */
final readonly class ImportEdiAction
{
    public function __construct(
        private EdiImportService $importer,
        private ScoringService $scoring,
        private CategoryResolver $categories,
    ) {}

    /**
     * @param  bool  $notify  rozeslat potvrzovací e-maily (jednotlivé nahrání ano,
     *                        hromadný admin import ne)
     */
    public function execute(EdiLog $log, bool $notify = true): VkvpaData
    {
        $h = $log->header;
        $pcall = $h->pCall();

        $this->assertTDateIsContestDay($h->tDate());

        $idKola = $this->scoring->koloForTDate($h->tDate()) ?? 0;

        Context::add('znacka', $pcall);
        Context::add('id_kola', $idKola);

        if ($idKola === 0) {
            throw new RoundNotFoundException($h->tDate());
        }

        $this->assertTDateMatchesQsos($h->tDate(), $log->qsos);

        if (VkvpaData::query()->where('znacka', $pcall)->where('id_kola', $idKola)->exists()) {
            throw new DuplicateEdiException($pcall);
        }

        try {
            $idKategorie = $this->categories->resolve($pcall, $h->pBand(), $h->pSect());
        } catch (UnknownBandException) {
            throw new UnknownBandException(
                'Nerozpoznané pásmo v deníku ('.$h->pBand().') – nelze určit kategorii. Oprav PBand a nahraj znovu.',
            );
        }

        if ($idKategorie === null) {
            throw new UnknownSectionException($h->pSect());
        }

        try {
            $data = DB::transaction(function () use ($log, $h, $pcall, $idKola, $idKategorie): VkvpaData {
                $head = $this->importer->import($log, $idKola);
                $score = $this->scoring->scoreEdi($head);

                return VkvpaData::create([
                    'id_kola' => $idKola,
                    'id_kategorie' => $idKategorie,
                    'znacka' => $pcall,
                    'locator' => $h->pWWLo(),
                    'jmeno' => $h->rName(),
                    'mail' => $h->rEmail(),
                    'telefon' => $h->rPhon(),
                    'soapbox' => $h->get('RSoap'),
                    'pocet' => $score->pocet,
                    'nasobice' => $score->nasobice,
                    'bodu_za_qso' => $score->boduZaQso,
                    'body' => $score->body,
                    'qrp' => $h->isQrp(),
                    'lp' => $h->isLp(),
                    'EDI' => true,
                    'EDI_ID' => $head->id,
                    'schvaleno' => false,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateEdiException($pcall);
        }

        if ($notify) {
            // defer() odešle event až po odeslání HTTP odpovědi – queue dispatch
            // nebrzdí redirect k uživateli. Transakce jsou v tomto bodě vždy uzavřeny.
            defer(static fn () => EdiImported::dispatch($data));
        }

        return $data;
    }

    /**
     * Ověří, že TDate odpovídá termínu závodu. Kolo VKV PA se koná vždy třetí
     * neděli v měsíci; TDate ale může být i dvoudenní rozsah (start;end), když
     * účastník použil šablonu 24h závodu. Stačí proto, aby třetí neděli
     * odpovídalo alespoň jedno z dat uvedených v TDate.
     *
     * @throws TDateNotContestDayException
     */
    private function assertTDateIsContestDay(string $tdate): void
    {
        $dates = $this->parseTDateDates($tdate);
        if ($dates === []) {
            // Nečitelné TDate řeší jiná validace (TDateMismatchException / EdiValidator).
            return;
        }

        if (array_any(
            $dates,
            fn (CarbonImmutable $d): bool => $d->isSameDay(ContestCalendar::thirdSundayOf((int) $d->year, (int) $d->month)),
        )) {
            return;
        }

        throw new TDateNotContestDayException($tdate);
    }

    /**
     * Vytáhne z TDate všechna validní data ve formátu YYYYMMDD (jedno i rozsah).
     *
     * @return CarbonImmutable[]
     */
    private function parseTDateDates(string $tdate): array
    {
        $dates = [];

        foreach (preg_split('/[^0-9]+/', trim($tdate)) ?: [] as $token) {
            if (strlen($token) !== 8) {
                continue;
            }

            try {
                $date = CarbonImmutable::createFromFormat('!Ymd', $token, 'UTC');
            } catch (Throwable) {
                continue;
            }

            // createFromFormat tichý overflow (např. měsíc 13) přeskočíme.
            if ($date instanceof CarbonImmutable && $date->format('Ymd') === $token) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * @param  EdiQso[]  $qsos
     *
     * @throws TDateMismatchException
     */
    private function assertTDateMatchesQsos(string $tdate, array $qsos): void
    {
        // TDate může být i rozsah (start;end) – QSO musí sednout s kterýmkoli z jeho dnů.
        $tdateDays = array_map(
            static fn (CarbonImmutable $d): string => $d->format('ymd'),
            $this->parseTDateDates($tdate),
        );
        $qsoDays = array_values(array_unique(array_map(static fn (EdiQso $q): string => $q->date, $qsos)));

        if ($tdateDays === [] || $qsoDays === []) {
            return;
        }

        if (array_intersect($tdateDays, $qsoDays) === []) {
            $preview = implode(', ', array_map($this->formatEdiDate(...), array_slice($qsoDays, 0, 3)));
            throw new TDateMismatchException($tdate, $preview);
        }
    }

    /** Datum spojení YYMMDD (např. „260118") → čitelné „18.01.2026". */
    private function formatEdiDate(string $yymmdd): string
    {
        return strlen($yymmdd) === 6
            ? sprintf('%d. %d. 20%s', (int) substr($yymmdd, 4, 2), (int) substr($yymmdd, 2, 2), substr($yymmdd, 0, 2))
            : $yymmdd;
    }
}
