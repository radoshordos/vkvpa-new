<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Výsledek automatického ohodnocení deníku z edilines.
 *
 * Vzorec dle reálné verze edit_hlaseni.php (v4.1.3):
 *   pocet    = počet QSO do CIZÍCH velkých čtverců (mimo domácí)
 *   nasobice = počet různých cizích velkých čtverců + 1 (domácí čtverec)
 *   body     = pocet * nasobice
 */
final readonly class EdiScore
{
    public function __construct(
        public int $pocet,
        public int $nasobice,
        public int $body,
    ) {
    }
}
