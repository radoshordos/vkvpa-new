<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Závodní časové okno (UTC) – QSO mimo se nezapočítávají.
 *
 * Hodnoty jsou konfigurovatelné přes config('vkvpa.contest_window.*').
 * Legacy pravidlo: `time BETWEEN 0800 AND 1100`
 */
final class ContestWindow
{
    public static function from(): string
    {
        return (string) config('vkvpa.contest_window.from', '0800');
    }

    public static function to(): string
    {
        return (string) config('vkvpa.contest_window.to', '1100');
    }
}
