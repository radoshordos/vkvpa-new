<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

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
     * Deaktivuje kola, jimž už uplynula uzávěrka (`datum_uzaverky` < teď).
     *
     * Slouží naplánované úloze ({@see Schedule} v
     * routes/console.php) – po uzávěrce se kolo přestane nabízet pro příjem
     * hlášení. Záložní logika {@see VkvpaKola::isActive()} (čerstvá neschválená
     * data) zůstává nedotčena, takže rozpracované deníky se neztratí.
     *
     * @return int počet deaktivovaných kol
     */
    public function deactivateExpiredRounds(): int
    {
        return VkvpaKola::query()
            ->where('aktivni', true)
            ->whereNotNull('datum_uzaverky')
            ->where('datum_uzaverky', '<', Carbon::now())
            ->update(['aktivni' => false]);
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
        $home = strtoupper(substr(trim((string) $head->PWWLo), 0, 4));
        // Den závodu = YYMMDD ze začátku TDate (formát YYYYMMDD;YYYYMMDD).
        $den = substr(trim((string) $head->TDate), 2, 6);

        $squares = $head->lines()
            ->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])
            ->when($den !== '', fn ($q) => $q->where('Date', $den))
            ->get(['Received-WWL'])
            ->map(static fn ($l): string => strtoupper(substr(trim($l->receivedWwl()), 0, 4)))
            ->filter(static fn (string $sq): bool => $sq !== '')
            ->values();

        $pocet = $squares->count();
        // Body za spojení přepočítáme z lokátorů, ne z deníku.
        $boduZaQso = $squares->sum(static fn (string $sq): int => Maidenhead::qsoPoints($home, $sq));

        // Násobiče: různé velké čtverce, se kterými bylo pracováno, + vždy vlastní.
        $unique = $squares->all();
        if ($home !== '') {
            $unique[] = $home;
        }
        $nasobice = count(array_unique($unique));
        $body = $boduZaQso * $nasobice;

        return new EdiScore(pocet: $pocet, boduZaQso: $boduZaQso, nasobice: $nasobice, body: $body);
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

        $id = VkvpaKola::query()
            ->whereYear('datum_konani', $year)
            ->whereMonth('datum_konani', $month)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    /**
     * Roční výsledky: součet bodů přes kola roku, po kategoriích a značkách.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, VkvpaData>
     */
    public function yearlyResults(int $year, bool $qrpOnly = false): Collection
    {
        // Roční výsledky jsou drahá agregace, která se mezi vyhodnoceními kol nemění.
        // Cache::flexible (stale-while-revalidate): do `fresh` s vrací z cache, do
        // `stale` s vrací starou hodnotu a obnoví ji na pozadí. Invaliduje se cíleně
        // v {@see self::rankRound()} přes forgetYearlyCache().
        return Cache::flexible(
            $this->yearlyCacheKey($year, $qrpOnly),
            [Config::integer('vkvpa.yearly_cache_fresh', 300), Config::integer('vkvpa.yearly_cache_stale', 1800)],
            fn (): Collection => $this->computeYearlyResults($year, $qrpOnly),
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, VkvpaData>
     */
    private function computeYearlyResults(int $year, bool $qrpOnly): Collection
    {
        $query = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->where('vkvpa_data.poradi', '<>', 0)
            ->where('vkvpa_kola.nazev', 'like', '%'.$year)
            ->selectRaw('vkvpa_data.id_kategorie as kategorie_id, vkvpa_data.znacka')
            ->selectRaw(
                'SUM(CASE WHEN vkvpa_data.EDI_ID = 0 AND vkvpa_data.id_kola >= ? THEN 0 ELSE vkvpa_data.body END) as celkem',
                [Config::integer('vkvpa.non_edi_nullify_from_kolo', 91)],
            )
            ->groupBy('vkvpa_data.id_kategorie', 'vkvpa_data.znacka')
            ->orderByDesc('celkem');

        if ($qrpOnly) {
            $query->where('vkvpa_data.qrp', true);
        }

        return $query->get();
    }

    /** Klíč cache ročních výsledků pro daný rok a QRP filtr. */
    private function yearlyCacheKey(int $year, bool $qrpOnly): string
    {
        return sprintf('vkvpa:yearly:%d:%d', $year, $qrpOnly ? 1 : 0);
    }

    /** Zahodí cache ročních výsledků daného roku (obě varianty QRP). */
    private function forgetYearlyCache(?int $year): void
    {
        if ($year === null) {
            return;
        }

        Cache::forget($this->yearlyCacheKey($year, false));
        Cache::forget($this->yearlyCacheKey($year, true));
    }

    /**
     * Rok kola pro invalidaci cache – stejná konvence jako yearlyResults()
     * (rok je na konci `nazev`, např. „1. kolo 2026").
     */
    private function yearOfRound(int $koloId): ?int
    {
        $nazev = VkvpaKola::query()->whereKey($koloId)->value('nazev');
        if (! is_string($nazev) || preg_match('/(\d{4})\s*$/', $nazev, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }
}
