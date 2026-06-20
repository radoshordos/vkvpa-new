<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Enums\QsoCountStatus;
use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiLog;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use App\Support\VkvpaSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vyhodnocení a skóre dle pravidel VKV PA.
 */
final class ScoringService
{
    /**
     * Přidělí pořadí (`poradi`) v rámci každé kategorie kola (husté: shoda = stejné).
     */
    public function rankRound(int $koloId): void
    {
        DB::transaction(function () use ($koloId): void {
            // Nepřevzaté (schvaleno=false) do žebříčku nepatří – vynulovat jim staré
            // pořadí, aby po odebrání převzetí nezůstalo viset zastaralé `poradi`.
            VkvpaData::query()
                ->where('id_kola', $koloId)
                ->where('schvaleno', false)
                ->where('poradi', '<>', 0)
                ->update(['poradi' => 0]);

            foreach (VkvpaKategorie::query()->pluck('id') as $kategorieId) {
                $entries = VkvpaData::query()
                    ->where('id_kola', $koloId)
                    ->approved()
                    ->where('id_kategorie', $kategorieId)
                    ->orderByDesc('body')
                    ->get(['id', 'body']);

                // Collect IDs grouped by rank first, then batch-update to avoid N+1.
                $counter = 0;
                $prevBody = null;
                /** @var array<int, int[]> $byRank */
                $byRank = [];
                foreach ($entries as $entry) {
                    if ($entry->body !== $prevBody) {
                        $counter++;
                        $prevBody = $entry->body;
                    }
                    $byRank[$counter][] = $entry->id;
                }

                foreach ($byRank as $rank => $ids) {
                    VkvpaData::query()->whereIn('id', $ids)->update(['poradi' => $rank]);
                }
            }
        });

        // Pořadí (a tím roční součty) se změnilo → zahodit cache ročních výsledků daného roku.
        $this->forgetYearlyCache($this->yearOfRound($koloId));
    }

    public function closeRound(int $koloId): void
    {
        VkvpaKola::query()->whereKey($koloId)->update(['vyhodnoceno' => Carbon::now()]);
    }

    /**
     * Automaticky vyhodnotí kolo, pokud na to dozrálo ({@see VkvpaKola::maBytVyhodnoceno()}:
     * po uzávěrce a buď všechny záznamy převzaté, nebo uplynula 20denní lhůta).
     * Přepočítá pořadí (a invaliduje cache ročních výsledků) a nastaví `vyhodnoceno`.
     *
     * @return bool true, pokud kolo bylo právě vyhodnoceno; false, pokud na to ještě nedozrálo
     */
    public function finalizeIfDue(VkvpaKola $kolo): bool
    {
        if (! $kolo->maBytVyhodnoceno()) {
            return false;
        }

        $this->rankRound($kolo->id);
        $this->closeRound($kolo->id);

        return true;
    }

    /**
     * Spočítá skóre deníku z QSO řádků (bodování per velký čtverec dle pravidel):
     *  - domácí velký čtverec = první 4 znaky PWWLo,
     *  - pocet     = započítaná QSO (včetně QSO do vlastního čtverce),
     *  - boduZaQso = součet bodů za spojení – přepočítáno z lokátorů (vlastní
     *    čtverec 2 body, sousední 3, každý další pás o bod víc); hodnota
     *    `QSO-Points` z deníku se ignoruje,
     *  - nasobice  = počet různých velkých čtverců včetně vlastního (vlastní vždy),
     *  - body      = boduZaQso * nasobice.
     *
     * Započítávají se jen QSO uvnitř závodního okna (den závodu dle `TDate`
     * a čas 08:00–11:00 UTC). QSO mimo okno mají efektivně bodovou hodnotu 0.
     */
    public function scoreEdi(Edihead $head): EdiScore
    {
        $home = Maidenhead::bigSquare((string) $head->p_wwlo);
        // Den závodu jako plné datum (Y-m-d) ze začátku t_date (YYYYMMDD;YYYYMMDD).
        $den = ContestWindow::dateFromTDate((string) $head->t_date);

        $squares = $head->lines()
            ->inContestWindow()
            ->when($den !== null, fn ($q) => $q->whereDate('qso_at', $den))
            ->get(['received_wwl'])
            ->map(static fn ($l): string => Maidenhead::bigSquare($l->receivedWwl))
            ->filter(static fn (string $sq): bool => $sq !== '')
            ->values()
            ->all();

        return $this->scoreSquares($home, $squares);
    }

    /**
     * Spočítá skóre deníku přímo z naparsovaného {@see EdiLog} – bez zápisu do DB.
     * Pravidla pro okno/den jsou identická se {@see scoreEdi()}; používá se pro
     * náhled při podání hlášení, než se deník uloží.
     */
    public function scoreLog(EdiLog $log): EdiScore
    {
        $home = Maidenhead::bigSquare($log->header->pWWLo());
        $den = ContestWindow::dayFromTDate($log->header->tDate());
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        $squares = [];
        foreach ($log->qsos as $qso) {
            $square = Maidenhead::bigSquare($qso->receivedWwl);
            if (QsoCountStatus::classify($qso->time, $qso->date, $square, $den, $from, $to)->isCounted()) {
                $squares[] = $square;
            }
        }

        return $this->scoreSquares($home, $squares);
    }

    /**
     * Společné jádro bodování: ze seznamu velkých čtverců (4 znaky, už
     * odfiltrované na závodní okno a den) spočítá pocet/boduZaQso/nasobice.
     *
     * @param  array<int, string>  $squares
     */
    private function scoreSquares(string $home, array $squares): EdiScore
    {
        $pocet = count($squares);
        // Body za spojení přepočítáme z lokátorů, ne z deníku.
        $boduZaQso = array_sum(array_map(static fn (string $sq): int => Maidenhead::qsoPoints($home, $sq), $squares));

        // Násobiče: různé velké čtverce, se kterými bylo pracováno, + vždy vlastní.
        $unique = $squares;
        if ($home !== '') {
            $unique[] = $home;
        }
        $nasobice = count(array_unique($unique));

        return new EdiScore(pocet: $pocet, boduZaQso: $boduZaQso, nasobice: $nasobice);
    }

    /**
     * Najde id kola podle data z hlavičky EDI (TDate, formát RRRRMMDD…).
     */
    public function koloForTDate(?string $tdate): ?int
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $tdate) ?? '';
        if (strlen($digits) < 6) {
            return null;
        }

        $year = (int) substr($digits, 0, 4);
        $month = (int) substr($digits, 4, 2);

        // Doménově existuje jen jedno kolo v měsíci (3. neděle); kdyby jich
        // omylem bylo víc, řazení zaručí deterministickou volbu nejstaršího.
        $id = VkvpaKola::query()
            ->whereYear('datum_konani', $year)
            ->whereMonth('datum_konani', $month)
            ->orderBy('datum_konani')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Roční výsledky: součet bodů přes kola roku (dle roku `datum_konani`),
     * po kategoriích a značkách.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VkvpaData>
     */
    public function yearlyResults(int $year, bool $qrpOnly = false, bool $lpOnly = false): Collection
    {
        // Roční výsledky jsou drahá agregace, která se mezi vyhodnoceními kol nemění.
        // Cache::flexible (stale-while-revalidate): do `fresh` s vrací z cache, do
        // `stale` s vrací starou hodnotu a obnoví ji na pozadí. Invaliduje se cíleně
        // v {@see self::rankRound()} přes forgetYearlyCache().
        //
        // Cachují se jen pole atributů, ne modely: `cache.serializable_classes`
        // je `false`, takže objekty by se z cache vrátily jako __PHP_Incomplete_Class.
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::flexible(
            $this->yearlyCacheKey($year, $qrpOnly, $lpOnly),
            [VkvpaSettings::yearlyCacheFresh(), VkvpaSettings::yearlyCacheStale()],
            fn (): array => $this->computeYearlyResults($year, $qrpOnly, $lpOnly)
                ->map(static fn (VkvpaData $row): array => $row->getAttributes())
                ->all(),
        );

        return VkvpaData::query()->hydrate($rows);
    }

    // Bodový výraz se započítáním pravidla NON_EDI_NULLIFY_FROM_KOLO (záznamy
    // bez EDI deníku v novějších kolech se počítají jako 0). Sdílený celkovým
    // součtem i měsíčním rozpadem; literal-string kvůli selectRaw na PHPStan L10.
    private const BODY_EXPR = 'SUM(CASE WHEN vkvpa_data.edihead_id IS NULL AND vkvpa_data.id_kola >= ? THEN 0 ELSE vkvpa_data.body END)';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, VkvpaData>
     */
    private function computeYearlyResults(int $year, bool $qrpOnly, bool $lpOnly): Collection
    {
        $nullifyFrom = VkvpaSettings::nonEdiNullifyFromKolo();

        $query = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->where('vkvpa_data.poradi', '<>', 0)
            ->whereYear('vkvpa_kola.datum_konani', $year)
            // MAX(jmeno): jméno se může mezi koly lišit, agregace potřebuje
            // deterministickou volbu přenositelnou na SQLite (testy).
            ->selectRaw('vkvpa_data.id_kategorie as kategorie_id, vkvpa_data.znacka, MAX(vkvpa_data.jmeno) as jmeno')
            ->selectRaw(self::BODY_EXPR.' as celkem', [$nullifyFrom])
            ->groupBy('vkvpa_data.id_kategorie', 'vkvpa_data.znacka')
            ->orderByDesc('celkem');

        if ($qrpOnly) {
            $query->onlyQrp();
        }

        if ($lpOnly) {
            $query->onlyLp();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, VkvpaData> $rows */
        $rows = $query->get();

        $this->attachMonthlyBreakdown($rows, $year, $qrpOnly, $lpOnly, $nullifyFrom);

        return $rows;
    }

    /**
     * Doplní každému řádku ročních výsledků atributy `mesic_1`..`mesic_12` s body
     * za jednotlivé měsíce roku (měsíční přehled). Sloupce jsou pevně 1–12, takže
     * se rok ukazuje celý (i měsíce bez závodu / budoucí). Počítá se samostatným
     * seskupením podle (kategorie, značka, kolo), aby se v hlavním dotazu nemusely
     * tvořit dynamické SQL aliasy (PHPStan L10 vyžaduje literal-string v selectRaw);
     * kolo se pak v PHP mapuje na svůj měsíc (víc kol v měsíci se sečte).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, VkvpaData>  $rows
     */
    private function attachMonthlyBreakdown(Collection $rows, int $year, bool $qrpOnly, bool $lpOnly, int $nullifyFrom): void
    {
        // id_kola → číslo měsíce (1..12).
        $koloMonth = [];
        foreach (VkvpaKola::query()->whereYear('datum_konani', $year)->get(['id', 'datum_konani']) as $kolo) {
            $koloMonth[$kolo->id] = (int) $kolo->datum_konani->format('n');
        }

        $breakdown = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->where('vkvpa_data.poradi', '<>', 0)
            ->whereYear('vkvpa_kola.datum_konani', $year)
            ->selectRaw('vkvpa_data.id_kategorie as kategorie_id, vkvpa_data.znacka, vkvpa_data.id_kola')
            ->selectRaw(self::BODY_EXPR.' as body', [$nullifyFrom])
            ->groupBy('vkvpa_data.id_kategorie', 'vkvpa_data.znacka', 'vkvpa_data.id_kola')
            ->when($qrpOnly, fn ($q) => $q->onlyQrp())
            ->when($lpOnly, fn ($q) => $q->onlyLp())
            ->get();

        // Mapa "kategorie|značka" => [měsíc => body] (víc kol v měsíci sečteno).
        $map = [];
        foreach ($breakdown as $b) {
            $month = $koloMonth[self::intAttr($b, 'id_kola')] ?? null;
            if ($month === null) {
                continue;
            }
            $key = self::strAttr($b, 'kategorie_id').'|'.self::strAttr($b, 'znacka');
            $map[$key][$month] = ($map[$key][$month] ?? 0) + self::intAttr($b, 'body');
        }

        foreach ($rows as $row) {
            $perMonth = $map[self::strAttr($row, 'kategorie_id').'|'.self::strAttr($row, 'znacka')] ?? [];
            // Pevně 12 měsíčních sloupců (i nulových) – ať je rok vidět celý a aby
            // přístup ve view nespadl na chybějícím atributu (strict mód modelu).
            for ($m = 1; $m <= 12; $m++) {
                $row->setAttribute('mesic_'.$m, $perMonth[$m] ?? 0);
            }
        }
    }

    /** Atribut agregovaného řádku jako int (agregáty se vrací jako mixed). */
    private static function intAttr(VkvpaData $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Atribut agregovaného řádku jako string – složka klíče mapy. */
    private static function strAttr(VkvpaData $model, string $key): string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) ? (string) $value : '';
    }

    /** Klíč cache ročních výsledků pro daný rok a kombinaci výkonových filtrů. */
    private function yearlyCacheKey(int $year, bool $qrpOnly, bool $lpOnly): string
    {
        // v6: řádky nově nesou měsíční rozpad `mesic_1`..`mesic_12` (v3 přidalo
        // agregované `jmeno`, v2 pole atributů bez něj, v1 serializovaná kolekce).
        return sprintf('vkvpa:yearly:v6:%d:%d:%d', $year, $qrpOnly ? 1 : 0, $lpOnly ? 1 : 0);
    }

    /** Zahodí cache ročních výsledků daného roku (všechny kombinace výkonových filtrů). */
    public function forgetYearlyCache(?int $year): void
    {
        if ($year === null) {
            return;
        }

        foreach ([false, true] as $qrp) {
            foreach ([false, true] as $lp) {
                Cache::forget($this->yearlyCacheKey($year, $qrp, $lp));
            }
        }
    }

    /**
     * Rok kola pro invalidaci cache – stejná konvence jako yearlyResults()
     * (rok z `datum_konani`).
     */
    private function yearOfRound(int $koloId): ?int
    {
        return VkvpaKola::query()->whereKey($koloId)->first(['datum_konani'])?->datum_konani->year;
    }
}
