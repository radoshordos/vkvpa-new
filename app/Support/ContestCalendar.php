<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Termíny závodních kol VKV PA.
 *
 * Kolo se koná třetí neděli každého měsíce od 08:00 do 11:00 UTC.
 * Upload window (příjem hlášení) trvá od 08:00 UTC v den závodu
 * do následujícího pátku 23:59:59 UTC.
 */
final class ContestCalendar
{
    /**
     * Třetí neděle daného roku/měsíce (datum, půlnoc UTC).
     */
    public static function thirdSundayOf(int $year, int $month): CarbonImmutable
    {
        $first = CarbonImmutable::parse(sprintf('%04d-%02d-01 00:00:00', $year, $month), 'UTC');
        // dayOfWeek: 0 = Sunday … 6 = Saturday
        $daysToFirstSunday = (7 - $first->dayOfWeek) % 7;

        return $first->addDays($daysToFirstSunday + 14);
    }

    /**
     * Čas zahájení kola: třetí neděle 08:00:00 UTC.
     */
    public static function roundStart(int $year, int $month): CarbonImmutable
    {
        return self::thirdSundayOf($year, $month)->setTime(8, 0, 0);
    }

    /**
     * Uzávěrka uploadu: pátek po závodu 23:59:59 UTC.
     * Od neděle (0) do pátku (5) = 5 dní.
     */
    public static function uploadDeadline(CarbonImmutable $contestDate): CarbonImmutable
    {
        return $contestDate
            ->startOfDay()
            ->addDays(5)
            ->setTime(23, 59, 59);
    }

    /**
     * Standardní název kola, např. "VKV PA 06/2026".
     */
    public static function roundName(int $year, int $month): string
    {
        return sprintf('VKV PA %02d/%d', $month, $year);
    }
}
