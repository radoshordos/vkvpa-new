<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Přepočítá pořadí v kole (ScoringService::rankRound).
 *
 * Spouští se vždy synchronně (dispatchSync) – rankRound je levný (pár UPDATE
 * v transakci) a přepočet pořadí i invalidace cache ročních výsledků tak
 * nezávisí na běžícím queue workeru.
 */
final class RankRoundJob
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
