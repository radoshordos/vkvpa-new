<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MapMode;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Mapové pohledy na spojení stanice. Tři akce odpovídající sloupci „Akce / EDI":
 *
 *   M – {@see jezek()}     „ježek": ze stanoviště vedou čáry do protistanic
 *   N – {@see spendliky()} špendlíky protistanic; popup = značka, vzdálenost, azimut
 *   S – {@see lokatory()}  velké čtverce (lokátory) s počtem protistanic v každém
 *
 * Všechny tři kreslí jen QSO uvnitř závodního okna 08:00–11:00 UTC.
 *
 * @api  Endpointy budou popsány v OpenAPI/Swagger – komentáře drží strukturu.
 */
class MapController extends Controller
{
    /**
     * Mapa „M" – ježek.
     *
     * Endpoint: GET /edi/{head}/mapa/jezek  (name: edi.mapa.jezek)
     * Výstup:   mapa s QTH uprostřed a čarami (paprsky) do všech protistanic.
     */
    public function jezek(Edihead $head): View
    {
        return $this->mapView($head, MapMode::Jezek);
    }

    /**
     * Mapa „N" – špendlíky s detailem spojení.
     *
     * Endpoint: GET /edi/{head}/mapa/spendliky  (name: edi.mapa.spendliky)
     * Výstup:   špendlík v místě každé protistanice; popup = značka, lokátor,
     *           vzdálenost v km a azimut ve stupních.
     */
    public function spendliky(Edihead $head): View
    {
        return $this->mapView($head, MapMode::Spendliky);
    }

    /**
     * Mapa „S" – velké čtverce (lokátory) s počtem protistanic.
     *
     * Endpoint: GET /edi/{head}/mapa/lokatory  (name: edi.mapa.lokatory)
     * Výstup:   ve středu každého obsazeného velkého čtverce (např. JN99) je
     *           značka s počtem protistanic z tohoto čtverce (= násobiče).
     */
    public function lokatory(Edihead $head): View
    {
        return $this->mapView($head, MapMode::Lokatory);
    }

    /**
     * Společná logika pro sestavení dat mapového pohledu.
     */
    private function mapView(Edihead $head, MapMode $mode): View
    {
        $home = $this->home($head);
        $withPoints = $mode !== MapMode::Lokatory;

        return view('pages.map', [
            'active' => '',
            'mode' => $mode,
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'points' => $withPoints ? $this->points($head, $home) : collect(),
            'squares' => $withPoints ? collect() : $this->squares($head),
        ]);
    }

    /**
     * Souřadnice domácího stanoviště (střed lokátoru z hlavičky), nebo null.
     *
     * @return array{lat: float, lon: float}|null
     */
    private function home(Edihead $head): ?array
    {
        return Maidenhead::toLatLon((string) $head->PWWLo);
    }

    /**
     * Protistanice v závodním okně se souřadnicemi, vzdáleností a azimutem.
     *
     * @param  array{lat: float, lon: float}|null  $home
     * @return Collection<int, array{lat: float, lon: float, call: string, wwl: string, points: int, dist: int|null, azimut: int|null}>
     */
    private function points(Edihead $head, ?array $home): Collection
    {
        return $head->lines()
            ->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])
            ->orderBy('Received-WWL')
            ->get(['lon', 'lat', 'CallSign', 'Received-WWL', 'QSO-Points'])
            ->map(function (Ediline $l) use ($home, $head): ?array {
                $lat = $l->lat;
                $lon = $l->lon;
                // Když chybí lon/lat, dopočítej ze středu lokátoru.
                $wwl = $l->receivedWwl();
                if (($lat === null || $lon === null) && $wwl !== '') {
                    $c = Maidenhead::toLatLon($wwl);
                    $lat = $c['lat'] ?? null;
                    $lon = $c['lon'] ?? null;
                }
                if ($lat === null || $lon === null) {
                    Log::debug('map.points.skip', [
                        'edihead_id' => $head->ID,
                        'call' => (string) $l->CallSign,
                        'wwl' => $wwl,
                    ]);

                    return null;
                }
                $lat = (float) $lat;
                $lon = (float) $lon;

                $dist = $home === null ? null : (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $lat, $lon));
                $azimut = $home === null ? null : (int) round(Maidenhead::bearingDeg($home['lat'], $home['lon'], $lat, $lon));

                return [
                    'lat' => $lat,
                    'lon' => $lon,
                    'call' => (string) $l->CallSign,
                    'wwl' => $wwl,
                    'points' => $l->qsoPoints(),
                    'dist' => $dist,
                    'azimut' => $azimut,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * Agregace protistanic do velkých čtverců (4 znaky lokátoru) s počtem QSO.
     *
     * @return Collection<int, array{square: string, count: int, lat: float, lon: float}>
     */
    private function squares(Edihead $head): Collection
    {
        $counts = [];
        foreach ($head->lines()->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])->get(['Received-WWL']) as $l) {
            $sq = strtoupper(substr(trim($l->receivedWwl()), 0, 4));
            if (preg_match('/^[A-R]{2}\d{2}$/', $sq)) {
                $counts[$sq] = ($counts[$sq] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($counts as $sq => $count) {
            $center = Maidenhead::bigSquareCenter($sq);
            if ($center === null) {
                Log::debug('map.squares.skip', [
                    'edihead_id' => $head->ID,
                    'square' => $sq,
                ]);

                continue;
            }
            $out[] = ['square' => (string) $sq, 'count' => $count, 'lat' => $center['lat'], 'lon' => $center['lon']];
        }

        return collect($out);
    }
}
