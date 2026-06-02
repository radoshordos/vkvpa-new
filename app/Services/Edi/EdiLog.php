<?php

declare(strict_types=1);

namespace App\Services\Edi;

/**
 * Kompletní naparsovaný EDI deník: hlavička + spojení + syrový zdroj.
 */
final readonly class EdiLog
{
    /**
     * @param list<EdiQso>  $qsos
     * @param list<string>  $lineErrors   řádky v sekci QSO, které neodpovídaly formátu
     * @param list<string>  $ignoredLines řádky vědomě přeskočené (značka „ERROR" z logovacího SW)
     */
    public function __construct(
        public EdiHeader $header,
        public array $qsos,
        public string $rawSource,
        public int $declaredTotal,
        public array $lineErrors = [],
        public array $ignoredLines = [],
    ) {
    }

    public function qsoCount(): int
    {
        return count($this->qsos);
    }
}
