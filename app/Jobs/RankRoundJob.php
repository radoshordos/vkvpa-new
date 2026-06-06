<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Scoring\ScoringService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Přepočítá pořadí v kole (ScoringService::rankRound) jako asynchronní úloha.
 * ShouldBeUnique zabrání souběžnému spuštění dvou jobů pro stejné kolo.
 */
final class RankRoundJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Maximální doba platnosti zámku unikátnosti v sekundách. */
    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $koloId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->koloId;
    }

    public function handle(ScoringService $scoring): void
    {
        $scoring->rankRound($this->koloId);
    }
}
