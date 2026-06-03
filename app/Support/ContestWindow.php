<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Config;

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
        return Config::string('vkvpa.contest_window.from', '0800');
    }

    public static function to(): string
    {
        return Config::string('vkvpa.contest_window.to', '1100');
    }
}
