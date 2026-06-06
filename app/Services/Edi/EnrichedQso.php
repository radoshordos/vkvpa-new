<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Enums\QsoMode;

/**
 * Jedno QSO obohacené o geometrii pro mapové a vizualizační pohledy:
 * souřadnice protistanice, body za spojení (z lokátorů), vzdálenost, azimut,
 * čas v minutách od půlnoci a druh provozu.
 */
final readonly class EnrichedQso
{
    public function __construct(
        public float $lat,
        public float $lon,
        public string $call,
        public string $wwl,
        public int $points,
        public ?int $dist,
        public ?int $azimut,
        public int $timeMinutes,
        public QsoMode $mode,
    ) {}
}
