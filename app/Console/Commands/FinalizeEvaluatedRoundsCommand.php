<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\Admin\ZaznamController;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Automaticky vyhodnotí (nastaví `vyhodnoceno`) kola, která na to dozrála:
 * příjem hlášení skončil a administrátor převzal všechny záznamy, nebo
 * uplynula 20denní záchranná lhůta od uzávěrky.
 *
 * Převzetí posledního záznamu po uzávěrce kolo vyhodnotí okamžitě
 * (viz {@see ZaznamController::update()}); tento
 * příkaz je denní záloha, která pokryje 20denní lhůtu i kola, kde se po
 * uzávěrce už nic nepřevzímá.
 *
 * Spouštěno každý den naplánovanou úlohou; ručně: `php artisan kola:finalize-evaluated`.
 */
final class FinalizeEvaluatedRoundsCommand extends Command
{
    protected $signature = 'kola:finalize-evaluated';

    protected $description = 'Vyhodnotí kola po uzávěrce (vše převzato nebo uplynula 20denní lhůta).';

    public function handle(ScoringService $scoring): int
    {
        $finalized = 0;

        // Kandidáti: nevyhodnocená kola po uzávěrce. Vlastní podmínku
        // (vše převzato / 20 dní) ověří finalizeIfDue přes maBytVyhodnoceno().
        $kola = VkvpaKola::query()
            ->whereNull('vyhodnoceno')
            ->where('datum_uzaverky', '<', now())
            ->get();

        foreach ($kola as $kolo) {
            if (! $scoring->finalizeIfDue($kolo)) {
                continue;
            }

            $this->info(sprintf('Vyhodnoceno: %s (id %d)', $kolo->nazev, $kolo->id));
            $finalized++;
        }

        if ($finalized > 0) {
            Log::info('schedule.kola.finalize_evaluated', ['finalized' => $finalized]);
        }

        return self::SUCCESS;
    }
}
