<?php

declare(strict_types=1);

namespace App\Services\Scoring;

/**
 * Kompletní výsledek debug analýzy EDI deníku: hlavička, parametry bodování,
 * souhrn parsování a rozpad jednotlivých QSO ({@see EdiDebugRow}).
 *
 * Slouží jen pro náhled – nic se neukládá do databáze. Hodnoty `pocet`,
 * `boduZaQso`, `nasobice` a `body` jsou shodné s {@see ScoringService::scoreEdi()}.
 */
final readonly class EdiDebugReport
{
    /**
     * @param  list<EdiDebugRow>  $rows  rozpad naparsovaných QSO
     * @param  list<string>  $ignoredLines  řádky, které neprošly parserem
     * @param  list<string>  $lineErrors  vadné řádky (z výjimky parseru)
     */
    public function __construct(
        public string $call,
        public string $locator,
        public string $homeSquare,
        public string $contestDay,
        public string $tDate,
        public string $band,
        public string $section,
        public int $power,
        public bool $qrp,
        public string $windowFrom,
        public string $windowTo,
        public int $declaredTotal,
        public int $parsedCount,
        public array $rows,
        public array $ignoredLines,
        public array $lineErrors,
        public int $pocet,
        public int $boduZaQso,
        public int $nasobice,
        public int $body,
        public int $excludedOutOfWindow,
        public int $excludedWrongDate,
        public int $ownSquareCount,
        public int $excludedEmpty,
        public int $duplicateCount,
        public ?int $categoryId = null,
        public ?string $categoryName = null,
    ) {}
}
