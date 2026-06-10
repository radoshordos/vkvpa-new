<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Scoring\ScoringService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Přepočítá pořadí v kole (ScoringService::rankRound).
 *
 * Spouští se synchronně (dispatchSync) – rankRound je levný (pár UPDATE
 * v transakci) a přepočet pořadí i invalidace cache ročních výsledků tak
 * nezávisí na běžícím queue workeru. Třída zůstává queueable pro případné
 * asynchronní použití; ShouldBeUnique pak zabrání souběhu pro stejné kolo.
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
