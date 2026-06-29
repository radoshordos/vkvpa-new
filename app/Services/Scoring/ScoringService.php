<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Enums\QsoCountStatus;
use App\Enums\Vykon;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\EdiRound;
use App\Services\Edi\EdiLog;
use App\Services\Edi\KoloStatistiky;
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
            // Nepřevzaté (approved=false) do žebříčku nepatří – vynulovat jim staré
            // pořadí, aby po odebrání převzetí nezůstalo viset zastaralé `poradi`.
            EdiEntry::query()
                ->where('round_id', $koloId)
                ->where('approved', false)
                ->where('rank', '<>', 0)
                ->update(['rank' => 0]);

            foreach (EdiCategory::query()->pluck('id') as $kategorieId) {
                $entries = EdiEntry::query()
                    ->where('round_id', $koloId)
                    ->approved()
                    ->where('category_id', $kategorieId)
                    ->orderByDesc('points')
                    ->get(['id', 'points']);

                // Collect IDs grouped by rank first, then batch-update to avoid N+1.
                $counter = 0;
                $prevBody = null;
                /** @var array<int, int[]> $byRank */
                $byRank = [];
                foreach ($entries as $entry) {
                    if ($entry->points !== $prevBody) {
                        $counter++;
                        $prevBody = $entry->points;
                    }
                    $byRank[$counter][] = $entry->id;
                }

                foreach ($byRank as $rank => $ids) {
                    EdiEntry::query()->whereIn('id', $ids)->update(['rank' => $rank]);
                }
            }
        });

        // Pořadí a veřejné souhrny se změnily → zahodit navázané cache.
        $this->forgetYearlyCache($this->yearOfRound($koloId));
        app(KoloStatistiky::class)->forgetRound($koloId);
        app(RekordyService::class)->forgetVrcholyCache();
    }

    public function closeRound(int $koloId): void
    {
        EdiRound::query()->whereKey($koloId)->update(['evaluated_at' => Carbon::now()]);
    }

    /**
     * Automaticky vyhodnotí kolo, pokud na to dozrálo ({@see EdiRound::maBytVyhodnoceno()}:
     * po uzávěrce a buď všechny záznamy převzaté, nebo uplynula 20denní lhůta).
     * Přepočítá pořadí (a invaliduje cache ročních výsledků) a nastaví `vyhodnoceno`.
     *
     * @return bool true, pokud kolo bylo právě vyhodnoceno; false, pokud na to ještě nedozrálo
     */
    public function finalizeIfDue(EdiRound $kolo): bool
    {
        if (! $kolo->shouldBeEvaluated()) {
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
     *  - multiplier  = počet různých velkých čtverců včetně vlastního (vlastní vždy),
     *  - body      = boduZaQso * multiplier.
     *
     * Započítávají se jen QSO uvnitř závodního okna (den závodu dle `TDate`
     * a čas 08:00–11:00 UTC). QSO mimo okno mají efektivně bodovou hodnotu 0.
     */
    public function scoreEdi(Edihead $head): EdiScore
    {
        $home = Maidenhead::bigSquare((string) $head->p_wwlo);
        // Den závodu = datum konání kola, do kterého deník patří (autoritativní);
        // fallback na den z TDate, když kolo neznáme. Bere se den konání, ne první
        // token TDate – u dvoudenního TDate (RRRRMMDD;RRRRMMDD) by jinak QSO ze
        // skutečného dne konání spadla jako „mimo den" a deník by dostal 0.
        $den = $this->contestDay($head->round_id, (string) $head->t_date)?->format('Y-m-d')
            ?? ContestWindow::dateFromTDate((string) $head->t_date);

        $squares = $head->lines()
            ->inContestWindow()
            ->completeExchange()
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
        // Den závodu z kola dle TDate (shodně se scoreEdi); fallback na den z TDate.
        $den = $this->contestDay(null, $log->header->tDate())?->format('ymd')
            ?? ContestWindow::dayFromTDate($log->header->tDate());
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        $squares = [];
        foreach ($log->qsos as $qso) {
            $square = Maidenhead::bigSquare($qso->receivedWwl);
            if (QsoCountStatus::classify($qso->receivedRst, $qso->receivedQsoNumber, $qso->time, $qso->date, $square, $den, $from, $to)->isCounted()) {
                $squares[] = $square;
            }
        }

        return $this->scoreSquares($home, $squares);
    }

    /**
     * Společné jádro bodování: ze seznamu velkých čtverců (4 znaky, už
     * odfiltrované na závodní okno a den) spočítá pocet/boduZaQso/multiplier.
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
        $multiplier = count(array_unique($unique));

        return new EdiScore(qsoCount: $pocet, qsoPoints: $boduZaQso, multiplier: $multiplier);
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
        $id = EdiRound::query()
            ->inYearMonth($year, $month)
            ->orderBy('starts_at')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Autoritativní den závodu pro skórování: datum konání kola, do kterého deník
     * patří. Přednost má předané `round_id` (uložený deník), jinak se kolo dohledá
     * podle TDate (rok+měsíc, {@see koloForTDate()}). Vrátí null, když odpovídající
     * kolo neexistuje – pak volající použije den odvozený přímo z TDate (fallback).
     *
     * Řeší rozpor dvoudenního TDate (RRRRMMDD;RRRRMMDD): den se bere z kola, ne
     * z prvního tokenu TDate, takže se započítají QSO ze skutečného dne konání.
     */
    public function contestDay(?int $idKola, ?string $tdate): ?Carbon
    {
        $id = $idKola ?? $this->koloForTDate($tdate);
        if ($id === null) {
            return null;
        }

        $den = EdiRound::query()->whereKey($id)->value('starts_at');

        return $den instanceof Carbon ? $den : null;
    }

    /**
     * Roční výsledky: součet bodů přes kola roku (dle roku `starts_at`),
     * po kategoriích a značkách.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, EdiEntry>
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
                ->map(static fn (EdiEntry $row): array => $row->getAttributes())
                ->all(),
        );

        return EdiEntry::query()->hydrate($rows);
    }

    // Bodový výraz se započítáním pravidla NON_EDI_NULLIFY_FROM_KOLO (záznamy
    // bez EDI deníku v novějších kolech se počítají jako 0). Sdílený celkovým
    // součtem i měsíčním rozpadem; literal-string kvůli selectRaw na PHPStan L10.
    private const BODY_EXPR = 'SUM(CASE WHEN edi_entries.edi_head_id IS NULL AND edi_entries.round_id >= ? THEN 0 ELSE edi_entries.points END)';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, EdiEntry>
     */
    private function computeYearlyResults(int $year, bool $qrpOnly, bool $lpOnly): Collection
    {
        $nullifyFrom = VkvpaSettings::nonEdiNullifyFromKolo();

        $query = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_entries.approved', true)
            ->where('edi_entries.rank', '<>', 0)
            ->whereYear('edi_rounds.starts_at', $year)
            // MAX(jmeno): jméno se může mezi koly lišit, agregace potřebuje
            // deterministickou volbu přenositelnou na SQLite (testy).
            ->selectRaw('edi_entries.category_id as kategorie_id, edi_entries.callsign, MAX(edi_entries.name) as name')
            ->selectRaw(self::BODY_EXPR.' as celkem', [$nullifyFrom])
            ->groupBy('edi_entries.category_id', 'edi_entries.callsign')
            ->orderByDesc('celkem');

        if ($qrpOnly) {
            $query->onlyQrp();
        }

        if ($lpOnly) {
            $query->onlyLp();
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, EdiEntry> $rows */
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, EdiEntry>  $rows
     */
    private function attachMonthlyBreakdown(Collection $rows, int $year, bool $qrpOnly, bool $lpOnly, int $nullifyFrom): void
    {
        // round_id → číslo měsíce (1..12).
        $koloMonth = [];
        foreach (EdiRound::query()->whereYear('starts_at', $year)->get(['id', 'starts_at']) as $kolo) {
            $koloMonth[$kolo->id] = (int) $kolo->starts_at->format('n');
        }

        $breakdown = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_entries.approved', true)
            ->where('edi_entries.rank', '<>', 0)
            ->whereYear('edi_rounds.starts_at', $year)
            ->selectRaw('edi_entries.category_id as kategorie_id, edi_entries.callsign, edi_entries.round_id')
            ->selectRaw('MAX(edi_rounds.name) as round_name, MAX(edi_rounds.starts_at) as starts_at')
            ->selectRaw(self::BODY_EXPR.' as body', [$nullifyFrom])
            ->selectRaw('MAX(edi_entries.qso_count) as qso_count_any, MAX(edi_entries.qso_points) as qso_points_any')
            ->selectRaw('MAX(edi_entries.multiplier) as multiplier_any, MAX(edi_entries.rank) as rank_any, MAX(edi_entries.edi_head_id) as edi_head_id_any')
            // Výkon za měsíc: v daném (kategorie, značka, kolo) je nejvýš jeden
            // záznam, MAX jen uspokojí GROUP BY (qrp/lp jsou 0/1). Aliasy záměrně
            // mimo názvy qrp/lp – ty model castuje na bool a intAttr() by je
            // přes is_numeric() přečetl jako 0.
            ->selectRaw('MAX(edi_entries.qrp) as qrp_any, MAX(edi_entries.lp) as lp_any')
            ->groupBy('edi_entries.category_id', 'edi_entries.callsign', 'edi_entries.round_id')
            ->when($qrpOnly, fn ($q) => $q->onlyQrp())
            ->when($lpOnly, fn ($q) => $q->onlyLp())
            ->get();

        // Mapa "kategorie|značka" => [měsíc => body] (víc kol v měsíci sečteno)
        // a paralelní mapa výkonu za měsíc (víc kol → ponecháme nejnižší výkon).
        $map = [];
        /** @var array<string, array<int, Vykon>> $vykonMap */
        $vykonMap = [];
        /** @var array<string, array<int, list<array<string, int|string|null>>>> $detailMap */
        $detailMap = [];
        foreach ($breakdown as $b) {
            $month = $koloMonth[self::intAttr($b, 'round_id')] ?? null;
            if ($month === null) {
                continue;
            }
            $key = self::strAttr($b, 'kategorie_id').'|'.self::strAttr($b, 'callsign');
            $map[$key][$month] = ($map[$key][$month] ?? 0) + self::intAttr($b, 'body');
            $detailMap[$key][$month][] = [
                'round_id' => self::intAttr($b, 'round_id'),
                'round_name' => self::strAttr($b, 'round_name'),
                'starts_at' => substr(self::strAttr($b, 'starts_at'), 0, 10),
                'qso_count' => self::intAttr($b, 'qso_count_any'),
                'qso_points' => self::intAttr($b, 'qso_points_any'),
                'multiplier' => self::intAttr($b, 'multiplier_any'),
                'points' => self::intAttr($b, 'body'),
                'rank' => self::intAttr($b, 'rank_any'),
                'edi_head_id' => self::intAttr($b, 'edi_head_id_any') ?: null,
            ];

            $vykon = Vykon::fromFlags(self::intAttr($b, 'qrp_any') === 1, self::intAttr($b, 'lp_any') === 1);
            $prev = $vykonMap[$key][$month] ?? null;
            if ($prev === null || $vykon->rank() > $prev->rank()) {
                $vykonMap[$key][$month] = $vykon;
            }
        }

        foreach ($rows as $row) {
            $rowKey = self::strAttr($row, 'kategorie_id').'|'.self::strAttr($row, 'callsign');
            $perMonth = $map[$rowKey] ?? [];
            $perVykon = $vykonMap[$rowKey] ?? [];
            $perDetail = $detailMap[$rowKey] ?? [];
            // Pevně 12 měsíčních sloupců (i nulových) – ať je rok vidět celý a aby
            // přístup ve view nespadl na chybějícím atributu (strict mód modelu).
            for ($m = 1; $m <= 12; $m++) {
                $row->setAttribute('mesic_'.$m, $perMonth[$m] ?? 0);
                $monthDetails = $perDetail[$m] ?? [];
                $row->setAttribute('detail_mesic_'.$m, $monthDetails);
                // Jen redukovaný výkon (QRP/LP) má smysl značit; plný = null.
                $v = $perVykon[$m] ?? null;
                $row->setAttribute('vykon_'.$m, $v !== null && $v->isReduced() ? $v->value : null);
            }
        }
    }

    /** Atribut agregovaného řádku jako int (agregáty se vrací jako mixed). */
    private static function intAttr(EdiEntry $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Atribut agregovaného řádku jako string – složka klíče mapy. */
    private static function strAttr(EdiEntry $model, string $key): string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) ? (string) $value : '';
    }

    /** Klíč cache ročních výsledků pro daný rok a kombinaci výkonových filtrů. */
    private function yearlyCacheKey(int $year, bool $qrpOnly, bool $lpOnly): string
    {
        // v8: řádky nově nesou detail pro odkazy z měsíčních buněk
        // (`detail_mesic_1`..`detail_mesic_12`).
        return sprintf('vkvpa:yearly:v8:%d:%d:%d', $year, $qrpOnly ? 1 : 0, $lpOnly ? 1 : 0);
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
     * (rok z `starts_at`).
     */
    private function yearOfRound(int $koloId): ?int
    {
        return EdiRound::query()->whereKey($koloId)->first(['starts_at'])?->starts_at->year;
    }
}
