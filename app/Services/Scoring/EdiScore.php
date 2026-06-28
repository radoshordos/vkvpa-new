<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Výsledek automatického ohodnocení deníku z edilines.
 *
 * Vzorec dle pravidel VKV PA (bodování per velký čtverec):
 *   qsoCount  = počet započítaných QSO (vč. QSO do vlastního čtverce)
 *   qsoPoints = součet bodů za spojení – přepočítáno z lokátorů (vlastní čtverec
 *               2, sousední 3, každý další pás o bod víc); QSO-Points z EDI se ignoruje
 *   multiplier  = počet různých velkých čtverců včetně vlastního (vlastní vždy)
 *   points    = PHP 8.4 property hook: qsoPoints * multiplier, žádný backing store
 *
 * Třída není `readonly class`, protože PHP 8.4 neumožňuje readonly na hooked property;
 * jednotlivé parametry jsou explicitně `readonly`.
 */
final class EdiScore
{
    public int $points {
        get => $this->qsoPoints * $this->multiplier;
    }

    public function __construct(
        public readonly int $qsoCount,
        public readonly int $qsoPoints,
        public readonly int $multiplier,
    ) {}
}
