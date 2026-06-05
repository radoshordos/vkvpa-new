<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\DuplicateEdiException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\UnknownBandException;
use App\Models\VkvpaData;
use App\Services\Edi\CategoryResolver;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiQso;
use App\Services\Scoring\ScoringService;

/**
 * Orchestruje celý tok importu EDI deníku: validace business pravidel,
 * uložení do DB, scoring a vytvoření rezervovaného řádku ve vkvpa_data.
 *
 * Vyhodí výjimku při jakémkoli selhání; úspěch vrací nový VkvpaData řádek.
 *
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

    public function execute(EdiLog $log): VkvpaData
    {
        $h = $log->header;
        $pcall = $h->pCall();

        $this->assertTDateMatchesQsos($h->tDate(), $log->qsos);

        $idKola = $this->scoring->koloForTDate($h->tDate()) ?? 0;

        if (VkvpaData::query()->hasEdi()->where('znacka', $pcall)->where('id_kola', $idKola)->exists()) {
            throw new DuplicateEdiException($pcall);
        }

        try {
            $idKategorie = $this->categories->resolve($pcall, $h->pBand(), $h->pSect()) ?? 0;
        } catch (UnknownBandException) {
            throw new UnknownBandException(
                'Nerozpoznané pásmo v deníku ('.$h->pBand().') – nelze určit kategorii. Oprav PBand a nahraj znovu.',
            );
        }

        $head = $this->importer->import($log);
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
            'EDI' => true,
            'EDI_ID' => $head->ID,
            'schvaleno' => false,
        ]);
    }

    /**
     * @param  EdiQso[]  $qsos
     *
     * @throws TDateMismatchException
     */
    private function assertTDateMatchesQsos(string $tdate, array $qsos): void
    {
        $tdateDay = substr(trim($tdate), 2, 6);
        $qsoDays = array_values(array_unique(array_map(static fn (EdiQso $q): string => $q->date, $qsos)));

        if ($tdateDay !== '' && $qsoDays !== [] && ! in_array($tdateDay, $qsoDays, true)) {
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
