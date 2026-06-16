<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder;

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
     * Začátek okna jako SQL TIME „HH:MM:SS" – pro filtr nad sloupcem qso_at
     * ({@see Builder::whereTime()}).
     */
    public static function fromSqlTime(): string
    {
        return self::hhmmToSqlTime(self::from());
    }

    /** Konec okna jako SQL TIME „HH:MM:SS" (viz {@see self::fromSqlTime()}). */
    public static function toSqlTime(): string
    {
        return self::hhmmToSqlTime(self::to());
    }

    /**
     * Extrahuje den závodu (YYMMDD) z TDate hlavičky EDI (formát YYYYMMDD nebo
     * YYYYMMDD;YYYYMMDD). Pro prázdný vstup vrátí prázdný řetězec.
     */
    public static function dayFromTDate(string $tdate): string
    {
        return substr(trim($tdate), 2, 6);
    }

    /**
     * Den závodu jako plné datum „Y-m-d" z TDate (YYYYMMDD…), nebo null když
     * z TDate datum nelze určit. Slouží k filtru `whereDate('qso_at', …)`, který
     * (na rozdíl od porovnání řetězce YYMMDD) potřebuje čtyřmístný rok.
     */
    public static function dateFromTDate(string $tdate): ?string
    {
        $digits = substr(trim($tdate), 0, 8);
        if (! preg_match('/^\d{8}$/', $digits)) {
            return null;
        }

        return substr($digits, 0, 4).'-'.substr($digits, 4, 2).'-'.substr($digits, 6, 2);
    }

    /** „HHMM" → SQL TIME „HH:MM:SS". */
    private static function hhmmToSqlTime(string $hhmm): string
    {
        return substr($hhmm, 0, 2).':'.substr($hhmm, 2, 2).':00';
    }
}
