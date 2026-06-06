<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Výsledek automatického ohodnocení deníku z edilines.
 *
 * Vzorec dle pravidel VKV PA (bodování per velký čtverec):
 *   pocet     = počet započítaných QSO (vč. QSO do vlastního čtverce)
 *   boduZaQso = součet bodů za spojení – přepočítáno z lokátorů (vlastní čtverec
 *               2, sousední 3, každý další pás o bod víc); QSO-Points z EDI se ignoruje
 *   nasobice  = počet různých velkých čtverců včetně vlastního (vlastní vždy)
 *   body      = PHP 8.4 property hook: boduZaQso * nasobice, žádný backing store
 *
 * Třída není `readonly class`, protože PHP 8.4 neumožňuje readonly na hooked property;
 * jednotlivé parametry jsou explicitně `readonly`.
 */
final class EdiScore
{
    public int $body {
        get => $this->boduZaQso * $this->nasobice;
    }

    public function __construct(
        public readonly int $pocet,
        public readonly int $boduZaQso,
        public readonly int $nasobice,
    ) {}
}
