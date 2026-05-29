<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Support\Maidenhead;
use Illuminate\View\View;

/**
 * Mapa spojení stanice (Fáze 9). Sjednocuje 7 duplicitních map*.php
 * (OpenLayers 4.6.4 i různé Leaflety) do jednoho řešení na Leafletu 1.9.4.
 */
class MapController extends Controller
{
    /** Soutěžní okno (legacy filtr `time BETWEEN 0800 and 1100`). */
    private const QSO_FROM = '0800';
    private const QSO_TO = '1100';

    public function show(Edihead $head): View
    {
        $homeLoc = $head->PWWLo;
        $home = Maidenhead::toLatLon((string) $homeLoc);

        $points = $head->lines()
            ->whereBetween('Time', [self::QSO_FROM, self::QSO_TO])
            ->orderBy('Received-WWL')
            ->get(['lon', 'lat', 'CallSign', 'Received-WWL', 'QSO-Points'])
            ->map(function ($l): ?array {
                $lat = $l->lat;
                $lon = $l->lon;
                // Když chybí lon/lat, dopočítej z lokátoru.
                if (($lat === null || $lon === null) && $l->{'Received-WWL'}) {
                    $c = Maidenhead::toLatLon((string) $l->{'Received-WWL'});
                    $lat = $c['lat'] ?? null;
                    $lon = $c['lon'] ?? null;
                }
                if ($lat === null || $lon === null) {
                    return null;
                }

                return [
                    'lat' => (float) $lat,
                    'lon' => (float) $lon,
                    'call' => (string) $l->CallSign,
                    'wwl' => (string) $l->{'Received-WWL'},
                    'points' => (int) $l->{'QSO-Points'},
                ];
            })
            ->filter()
            ->values();

        return view('pages.map', [
            'active' => '',
            'pcall' => (string) $head->PCall,
            'home' => $home,
            'homeLoc' => (string) $homeLoc,
            'points' => $points,
        ]);
    }
}
