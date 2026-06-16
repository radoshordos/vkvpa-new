<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Závodní časové okno (UTC) – QSO mimo se nezapočítávají.
 *
 * Hodnoty jsou konfigurovatelné přes config('vkvpa.contest_window.*').
 * Výchozí pravidlo: `time BETWEEN 0800 AND 1100` (UTC).
 */
final class ContestWindow
{
    public static function from(): string
    {
        return VkvpaSettings::contestWindowFrom();
    }

    public static function to(): string
    {
        return VkvpaSettings::contestWindowTo();
    }

    /**
     * Extrahuje den závodu (YYMMDD) z TDate hlavičky EDI (formát YYYYMMDD nebo
     * YYYYMMDD;YYYYMMDD). Pro prázdný vstup vrátí prázdný řetězec.
     */
    public static function dayFromTDate(string $tdate): string
    {
        return substr(trim($tdate), 2, 6);
    }
}
