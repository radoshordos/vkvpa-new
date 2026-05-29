<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vyhodnocení a skóre (Fáze 7).
 *
 * Nahrazuje: vyhodnoceni.php (pořadí), uzavreni.php (uzávěrka) a rekonstruuje
 * DB pohled `vysledky` (skóre z deníku). Vše přes Eloquent, bez SQL injection.
 */
final class ScoringService
{
    /**
     * Od tohoto kola se hlášení bez EDI deníku do ročního součtu nezapočítávají
     * (legacy `$edikolo = 91`).
     */
    private const int NON_EDI_NULLIFY_FROM_KOLO = 91;

    /**
     * Přidělí pořadí (`poradi`) v rámci každé kategorie kola.
     * Husté pořadí: shodný počet bodů = stejné pořadí (1, 2, 2, 3…).
     * Nahrazuje vyhodnoceni.php.
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
                    ->get();

                $counter = 0;
                $prevBody = null;
                foreach ($entries as $entry) {
                    if ($entry->body !== $prevBody) {
                        $counter++;
                    }
                    $entry->update(['poradi' => $counter]);
                    $prevBody = $entry->body;
                }
            }
        });
    }

    /**
     * Uzavře kolo (nastaví `vyhodnoceno`). Nahrazuje uzavreni.php.
     */
    public function closeRound(int $koloId): void
    {
        VkvpaKola::query()
            ->whereKey($koloId)
            ->update(['vyhodnoceno' => Carbon::now()]);
    }

    /**
     * Spočítá skóre deníku z jeho QSO řádků (rekonstrukce pohledu `vysledky`).
     *
     * Předpoklady (dokumentované, neboť definice pohledu chyběla):
     *  - platné QSO = řádek s QSO-Points > 0,
     *  - lbody      = součet QSO-Points platných QSO,
     *  - lnasobic   = počet různých „velkých čtverců" (první 4 znaky WWL).
     */
    public function scoreEdi(Edihead $head): EdiScore
    {
        $lines = $head->lines()
            ->where('QSO-Points', '>', 0)
            ->get(['QSO-Points', 'Received-WWL']);

        $lbody = (int) $lines->sum('QSO-Points');
        $platnych = $lines->count();
        $lnasobic = $lines
            ->map(static fn ($l): string => substr((string) $l->{'Received-WWL'}, 0, 4))
            ->filter(static fn (string $sq): bool => $sq !== '')
            ->unique()
            ->count();

        return new EdiScore(lbody: $lbody, lnasobic: $lnasobic, platnych: $platnych);
    }

    /**
     * Roční výsledky: součet bodů přes kola daného roku, po kategoriích a značkách.
     * Pravidlo nulování ne-EDI hlášení od kola >= 91 (legacy).
     *
     * @return Collection<int, object{kategorie_id:int, znacka:string, celkem:int}>
     */
    public function yearlyResults(int $year, bool $qrpOnly = false): Collection
    {
        $query = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->where('vkvpa_data.poradi', '<>', 0)
            ->where('vkvpa_kola.nazev', 'like', "%{$year}")
            ->selectRaw('vkvpa_data.id_kategorie as kategorie_id, vkvpa_data.znacka')
            ->selectRaw(
                'SUM(IF(vkvpa_data.EDI_ID = 0 AND vkvpa_data.id_kola >= ?, 0, vkvpa_data.body)) as celkem',
                [self::NON_EDI_NULLIFY_FROM_KOLO],
            )
            ->groupBy('vkvpa_data.id_kategorie', 'vkvpa_data.znacka')
            ->orderByDesc('celkem');

        if ($qrpOnly) {
            $query->where('vkvpa_data.qrp', true);
        }

        return $query->get();
    }
}
