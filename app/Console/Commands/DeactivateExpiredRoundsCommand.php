<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Scoring\ScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deaktivuje kola, jimž uplynula uzávěrka – voláno naplánovanou úlohou jednou za hodinu.
 */
final class DeactivateExpiredRoundsCommand extends Command
{
    protected $signature = 'kola:deactivate-expired';

    protected $description = 'Deaktivuje kola po uplynutí uzávěrky (aktivni = false).';

    public function handle(ScoringService $scoring): int
    {
        $pocet = $scoring->deactivateExpiredRounds();

        if ($pocet > 0) {
            Log::info('schedule.kola.deactivate_expired', ['pocet' => $pocet]);
            $this->info("Deaktivováno {$pocet} kol.");
        }

        return self::SUCCESS;
    }
}
