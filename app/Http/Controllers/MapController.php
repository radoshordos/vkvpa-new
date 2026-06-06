<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MapMode;
use App\Models\Edihead;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\QsoGeometry;
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
 * Všechny tři kreslí jen QSO uvnitř závodního okna 08:00–11:00 UTC. Geometrii
 * (souřadnice, vzdálenost, azimut, čtverce) počítá sdílená {@see QsoGeometry}.
 *
 * @api  Endpointy budou popsány v OpenAPI/Swagger – komentáře drží strukturu.
 */
class MapController extends Controller
{
    public function __construct(private readonly QsoGeometry $geometry) {}

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
        $home = Maidenhead::toLatLon((string) $head->PWWLo);
        $withPoints = $mode !== MapMode::Lokatory;

        return view('pages.map', [
            'active' => '',
            'mode' => $mode,
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'points' => $withPoints ? $this->points($head, $home) : collect(),
            'squares' => $withPoints ? collect() : $this->geometry->bigSquares($head),
        ]);
    }

    /**
     * Protistanice v závodním okně se souřadnicemi, vzdáleností a azimutem.
     *
     * @param  array{lat: float, lon: float}|null  $home
     * @return Collection<int, array{lat: float, lon: float, call: string, wwl: string, points: int, dist: int|null, azimut: int|null}>
     */
    private function points(Edihead $head, ?array $home): Collection
    {
        return $this->geometry->enrichedQsos($head, $home, 'Received-WWL')
            ->map(fn (EnrichedQso $q): array => [
                'lat' => $q->lat,
                'lon' => $q->lon,
                'call' => $q->call,
                'wwl' => $q->wwl,
                'points' => $q->points,
                'dist' => $q->dist,
                'azimut' => $q->azimut,
            ]);
    }
}
