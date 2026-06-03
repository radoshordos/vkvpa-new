<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Support\ContestWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            foreach (VkvpaKategorie::query()->pluck('id') as $kategorieId) {
                $entries = VkvpaData::query()
                    ->where('id_kola', $koloId)
                    ->where('schvaleno', true)
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
    }

    public function closeRound(int $koloId): void
    {
        VkvpaKola::query()->whereKey($koloId)->update(['vyhodnoceno' => Carbon::now()]);
    }

    /**
     * Spočítá skóre deníku z QSO řádků (vzorec z edit_hlaseni.php v4.1.3):
     *  - domácí velký čtverec = první 4 znaky PWWLo,
     *  - pocet    = QSO do cizích velkých čtverců,
     *  - nasobice = počet různých cizích velkých čtverců + 1,
     *  - body     = pocet * nasobice.
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
            ->map(static fn ($l): string => strtoupper(substr(trim((string) $l->{'Received-WWL'}), 0, 4)))
            ->filter(static fn (string $sq): bool => $sq !== '' && $sq !== $home);

        $pocet = $squares->count();
        $nasobice = $squares->unique()->count() + 1;
        $body = $pocet * $nasobice;

        return new EdiScore(pocet: $pocet, nasobice: $nasobice, body: $body);
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
        $query = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->where('vkvpa_data.poradi', '<>', 0)
            ->where('vkvpa_kola.nazev', 'like', '%'.$year)
            ->selectRaw('vkvpa_data.id_kategorie as kategorie_id, vkvpa_data.znacka')
            ->selectRaw(
                'SUM(CASE WHEN vkvpa_data.EDI_ID = 0 AND vkvpa_data.id_kola >= ? THEN 0 ELSE vkvpa_data.body END) as celkem',
                [(int) config('vkvpa.non_edi_nullify_from_kolo', 91)],
            )
            ->groupBy('vkvpa_data.id_kategorie', 'vkvpa_data.znacka')
            ->orderByDesc('celkem');

        if ($qrpOnly) {
            $query->where('vkvpa_data.qrp', true);
        }

        return $query->get();
    }
}
