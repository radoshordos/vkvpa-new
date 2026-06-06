<?php

declare(strict_types=1);

namespace App\Services\Edi;

/**
 * Velký čtverec (4 znaky lokátoru, např. „JN99") se středem a počtem
 * protistanic z něj – pro mapu „S" a graf čtverců.
 */
final readonly class BigSquareCount
{
    public function __construct(
        public string $square,
        public int $count,
        public float $lat,
        public float $lon,
    ) {}
}
