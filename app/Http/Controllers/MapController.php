<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
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
 * Pozn.: model Edihead/Ediline má sloupce s nestandardními názvy (PWWLo,
 * Received-WWL…), proto je tento soubor v phpstan.neon → ignoreErrors
 * (identifier property.notFound).
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
        $home = $this->home($head);

        return view('pages.map', [
            'active' => '',
            'mode' => 'jezek',
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'points' => $this->points($head, $home),
            'squares' => collect(),
        ]);
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
        $home = $this->home($head);

        return view('pages.map', [
            'active' => '',
            'mode' => 'spendliky',
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'points' => $this->points($head, $home),
            'squares' => collect(),
        ]);
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
        $home = $this->home($head);

        return view('pages.map', [
            'active' => '',
            'mode' => 'lokatory',
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'points' => collect(),
            'squares' => $this->squares($head),
        ]);
    }

    /**
     * Souřadnice domácího QTH (z lokátoru hlavičky PWWLo).
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
            ->whereBetween('Time', [ContestWindow::FROM, ContestWindow::TO])
            ->orderBy('Received-WWL')
            ->get(['lon', 'lat', 'CallSign', 'Received-WWL', 'QSO-Points'])
            ->map(function ($l) use ($home): ?array {
                $lat = $l->lat;
                $lon = $l->lon;
                // Když chybí lon/lat, dopočítej ze středu lokátoru.
                if (($lat === null || $lon === null) && $l->{'Received-WWL'}) {
                    $c = Maidenhead::toLatLon((string) $l->{'Received-WWL'});
                    $lat = $c['lat'] ?? null;
                    $lon = $c['lon'] ?? null;
                }
                if ($lat === null || $lon === null) {
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
                    'wwl' => (string) $l->{'Received-WWL'},
                    'points' => (int) $l->{'QSO-Points'},
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
        foreach ($head->lines()->whereBetween('Time', [ContestWindow::FROM, ContestWindow::TO])->get(['Received-WWL']) as $l) {
            $sq = strtoupper(substr(trim((string) $l->{'Received-WWL'}), 0, 4));
            if (preg_match('/^[A-R]{2}\d{2}$/', $sq)) {
                $counts[$sq] = ($counts[$sq] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($counts as $sq => $count) {
            $center = Maidenhead::bigSquareCenter($sq);
            if ($center === null) {
                continue;
            }
            $out[] = ['square' => (string) $sq, 'count' => $count, 'lat' => $center['lat'], 'lon' => $center['lon']];
        }

        return collect($out);
    }
}
