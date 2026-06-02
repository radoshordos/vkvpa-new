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

    /**
     * Střed velkého čtverce (4 znaky, např. „JN99") – plocha 2° zem. délky × 1° šířky.
     * Používá mapa „S" (počet protistanic v každém velkém čtverci = násobiči).
     *
     * @return array{lat: float, lon: float}|null  null při neplatném čtverci
     */
    public static function bigSquareCenter(string $square): ?array
    {
        $sq = strtoupper(trim($square));
        if (! preg_match('/^[A-R]{2}\d{2}$/', $sq)) {
            return null;
        }

        $lon = (ord($sq[0]) - ord('A')) * 20 - 180 + ((int) $sq[2]) * 2 + 1.0;  // +1° = střed z 2°
        $lat = (ord($sq[1]) - ord('A')) * 10 - 90 + (int) $sq[3] + 0.5;         // +0.5° = střed z 1°

        return ['lat' => round($lat, 6), 'lon' => round($lon, 6)];
    }

    /**
     * Vzdálenost mezi dvěma body v kilometrech (haversine, poloměr Země 6371 km).
     * Pro popup mapy „N" (vzdálenost spojení).
     */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Azimut (kompasový směr) z bodu 1 do bodu 2 ve stupních 0–360 (0 = sever).
     * Pro popup mapy „N" (směr na protistanici).
     */
    public static function bearingDeg(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLon = deg2rad($lon2 - $lon1);
        $y = sin($dLon) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2))
            - sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dLon);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }
}
