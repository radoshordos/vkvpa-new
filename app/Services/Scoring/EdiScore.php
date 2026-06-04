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
 *   body      = boduZaQso * nasobice
 */
final readonly class EdiScore
{
    public function __construct(
        public int $pocet,
        public int $boduZaQso,
        public int $nasobice,
        public int $body,
    ) {}
}
