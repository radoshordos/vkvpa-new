<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Převod Maidenhead QTH lokátoru (např. „JN99AJ") na zeměpisné souřadnice
 * (střed čtverce). Nahrazuje ruční výpočet (`myc`) z legacy map souborů.
 */
final class Maidenhead
{
    /**
     * @return array{lat: float, lon: float}|null  null při neplatném lokátoru
     */
    public static function toLatLon(string $locator): ?array
    {
        $loc = strtoupper(trim($locator));
        if (! preg_match('/^[A-R]{2}\d{2}[A-X]{2}$/', $loc)) {
            return null;
        }

        $lon = (ord($loc[0]) - ord('A')) * 20 - 180;
        $lat = (ord($loc[1]) - ord('A')) * 10 - 90;

        $lon += ((int) $loc[2]) * 2;
        $lat += (int) $loc[3];

        $lon += (ord($loc[4]) - ord('A')) * (2 / 24);
        $lat += (ord($loc[5]) - ord('A')) * (1 / 24);

        // Posun na střed subčtverce.
        $lon += (2 / 24) / 2;
        $lat += (1 / 24) / 2;

        return ['lat' => round($lat, 6), 'lon' => round($lon, 6)];
    }
}
