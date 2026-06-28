<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EdiRound;
use App\Services\Edi\KoloStatistiky;
use App\Services\Scoring\RekordyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Předpočítá all-time ODX (nejdelší spojení napříč všemi vyhodnocenými koly)
 * pro síň slávy na stránce Statistiky. Výpočet je drahý (projde QSO všech
 * deníků), proto se nedělá při web requestu, ale touto úlohou; každé kolo
 * využívá cache {@see KoloStatistiky::prehled()} (zároveň ji tím zahřeje).
 *
 * Spouštěno týdně; ručně: `php artisan statistiky:precompute-odx`.
 */
final class PrecomputeOdxCommand extends Command
{
    protected $signature = 'statistiky:precompute-odx';

    protected $description = 'Předpočítá all-time ODX (nejdelší spojení historie) pro síň slávy.';

    public function handle(KoloStatistiky $statistiky, RekordyService $rekordy): int
    {
        /** @var array{dist: int, call: string, wwl: string, home: string, homeCall: string, kolo: string, koloId: int}|null $best */
        $best = null;

        $kola = EdiRound::query()
            ->whereNotNull('evaluated_at')
            ->orderBy('starts_at')
            ->get(['id', 'name', 'starts_at']);

        foreach ($kola as $kolo) {
            $odx = $statistiky->prehled($kolo)['odx'];
            if ($odx === null) {
                continue;
            }

            if ($best === null || $odx['dist'] > $best['dist']) {
                $best = [
                    'dist' => $odx['dist'],
                    'call' => $odx['call'],
                    'wwl' => $odx['wwl'],
                    'home' => $odx['home'],
                    'homeCall' => $odx['homeCall'],
                    'kolo' => $kolo->name,
                    'koloId' => $kolo->id,
                ];
            }
        }

        $rekordy->storeOdxAllTime($best);

        if ($best !== null) {
            $this->info(sprintf('All-time ODX: %d km (%s → %s, kolo %s)', $best['dist'], $best['homeCall'], $best['call'], $best['kolo']));
            Log::info('schedule.statistiky.precompute_odx', ['dist' => $best['dist'], 'round_id' => $best['koloId']]);
        } else {
            $this->warn('Žádné spojení k vyhodnocení (prázdná data).');
        }

        return self::SUCCESS;
    }
}
