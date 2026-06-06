<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Scoring\ScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Přepočítá pořadí v kole (ScoringService::rankRound) jako asynchronní úloha.
 */
final class RankRoundJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $koloId,
    ) {}

    public function handle(ScoringService $scoring): void
    {
        $scoring->rankRound($this->koloId);
    }
}
