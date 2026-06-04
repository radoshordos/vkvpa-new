<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Jeden QSO řádek v debug náhledu bodování – výsledek rozhodnutí, zda se
 * spojení započítává do skóre, a proč (ne).
 *
 * `reason` je strojový kód stavu:
 *   - counted        spojení se započítává,
 *   - out_of_window  čas mimo závodní okno,
 *   - wrong_date     datum neodpovídá dni závodu (TDate),
 *   - own_square     cíl je domácí velký čtverec,
 *   - empty_wwl      chybí přijatý lokátor.
 */
final readonly class EdiDebugRow
{
    public function __construct(
        public int $index,
        public string $date,
        public string $time,
        public string $callSign,
        public string $receivedWwl,
        public string $bigSquare,
        public bool $inWindow,
        public bool $dateMatches,
        public bool $isOwnSquare,
        public bool $isEmptySquare,
        public bool $counted,
        public bool $newMultiplier,
        public bool $duplicate,
        public string $reason,
    ) {}
}
