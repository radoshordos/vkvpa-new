<?php

declare(strict_types=1);

namespace App\Support;

use Location\Bearing\BearingSpherical;
use Location\Coordinate;
use Location\Distance\Vincenty;
use Location\Exception\NotConvergingException;

/**
 * Převod Maidenhead QTH lokátoru (např. „JN99AJ") na zeměpisné souřadnice
 * (střed čtverce).
 */
final class Maidenhead
{
    /**
     * Je řetězec platný Maidenhead lokátor? Akceptuje velký čtverec (4 znaky,
     * např. „JN99") i plný lokátor se subčtvercem (6 znaků, např. „JN99AJ").
     */
    public static function isValidLocator(string $locator): bool
    {
        return $locator
                |> trim(...)
                |> strtoupper(...)
                |> (fn ($x) => preg_match('/^[A-R]{2}\d{2}([A-X]{2})?$/', $x)) === 1;
    }

    /**
     * Velký čtverec (první 4 znaky lokátoru), normalizovaný na velká písmena
     * bez okolních mezer. Nevaliduje formát – pro ověření použij
     * {@see isValidLocator()} na výsledku.
     */
    public static function bigSquare(string $locator): string
    {
        return $locator
                |> trim(...)
                |> strtoupper(...)
                |> (fn ($x) => substr($x, 0, 4));
    }

    /**
     * Je řetězec platný velký čtverec (přesně 4 znaky Maidenhead, normalizováno)?
     * Vstup se před porovnáním ořízne a převede na velká písmena.
     */
    public static function isValidBigSquare(string $square): bool
    {
        return $square
                |> trim(...)
                |> strtoupper(...)
                |> (fn ($x) => preg_match('/^[A-R]{2}\d{2}$/', $x)) === 1;
    }

    /**
     * @return array{lat: float, lon: float}|null null při neplatném lokátoru
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
     * @return array{lat: float, lon: float}|null null při neplatném čtverci
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
     * Celočíselné souřadnice velkého čtverce v mřížce velkých čtverců
     * (x = zem. délka po 2°, y = zem. šířka po 1°).
     *
     * @return array{x: int, y: int}|null null při neplatném čtverci
     */
    public static function bigSquareGrid(string $square): ?array
    {
        $sq = strtoupper(trim($square));
        if (! preg_match('/^[A-R]{2}\d{2}$/', $sq)) {
            return null;
        }

        return [
            'x' => (ord($sq[0]) - ord('A')) * 10 + (int) $sq[2],
            'y' => (ord($sq[1]) - ord('A')) * 10 + (int) $sq[3],
        ];
    }

    /**
     * Pásová (ring) vzdálenost mezi dvěma velkými čtverci v jednotkách velkých
     * čtverců – Chebyshevova vzdálenost v mřížce. Vlastní čtverec = 0,
     * sousední (i diagonální) = 1, každý další pás +1.
     *
     * @return int|null null při neplatném vstupu
     */
    public static function bigSquareRingDistance(string $a, string $b): ?int
    {
        $ga = self::bigSquareGrid($a);
        $gb = self::bigSquareGrid($b);
        if ($ga === null || $gb === null) {
            return null;
        }

        return max(abs($ga['x'] - $gb['x']), abs($ga['y'] - $gb['y']));
    }

    /**
     * Body za spojení dle pravidel VKV PA: vlastní velký čtverec 2 body,
     * sousední 3 a v dalších pásech vždy o bod víc (2 + pásová vzdálenost).
     * Při neplatném lokátoru (vlastním či protistanice) vrací 0.
     */
    public static function qsoPoints(string $homeSquare, string $workedSquare): int
    {
        $ring = self::bigSquareRingDistance($homeSquare, $workedSquare);

        return $ring === null ? 0 : 2 + $ring;
    }

    /**
     * Vzdálenost mezi dvěma body v kilometrech (Vincenty, WGS-84).
     * Pro popup mapy „N" (vzdálenost spojení).
     */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        try {
            return new Vincenty()->getDistance(
                new Coordinate($lat1, $lon1),
                new Coordinate($lat2, $lon2),
            ) / 1000.0;
        } catch (NotConvergingException) {
            // Antipodální body – záložní haversine.
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat / 2) ** 2
                + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

            return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
        }
    }

    /**
     * Azimut (kompasový směr) z bodu 1 do bodu 2 ve stupních 0–360 (0 = sever).
     * Pro popup mapy „N" (směr na protistanici).
     */
    public static function bearingDeg(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return new BearingSpherical()->calculateBearing(
            new Coordinate($lat1, $lon1),
            new Coordinate($lat2, $lon2),
        );
    }
}
