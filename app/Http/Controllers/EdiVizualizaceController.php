<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\PorovnaniRivals;
use App\Services\Edi\QsoGeometry;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Komplexní vizualizace deníku: mapa (vč. přehrávání deníku) + grafy na jedné
 * stránce. Geometrii spojení (souřadnice, vzdálenost, azimut, body, čtverce)
 * počítá sdílená {@see QsoGeometry}, agregace pro grafy {@see DenikStatistiky}.
 * Doplňkové tabulky (TOP ODX, násobiče, nezapočítaná QSO) jsou na stránce
 * Vizuální inkubátor; porovnání s deníkem soupeře na stránce Porovnání deníků
 * ({@see EdiPorovnaniController}).
 */
class EdiVizualizaceController extends Controller
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly DenikStatistiky $statistiky,
        private readonly PorovnaniRivals $porovnani,
    ) {}

    public function show(Edihead $head): View
    {
        // Vizualizace je veřejná vždy (zobrazuje jen vlastní deník účastníka);
        // citlivá vrstva roundStations se vydává až po uzavření kola
        // (viz QsoGeometry).
        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = strtoupper(substr((string) $head->p_wwlo, 0, 4));

        $enriched = $this->geometry->enrichedQsos($head, $home, 'time');

        $fromMin = DenikStatistiky::minutes(ContestWindow::from());
        $toMin = DenikStatistiky::minutes(ContestWindow::to());

        $nasobice = $this->statistiky->noveNasobice($enriched, $homeSq);

        return view('pages.vizualizace', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->p_call,
            'homeLoc' => (string) $head->p_wwlo,
            'homeSq' => $homeSq,
            'home' => $home,
            'window' => ['from' => $fromMin, 'to' => $toMin],
            'mapPoints' => $enriched->map(fn (EnrichedQso $q): array => [
                'lat' => $q->lat,
                'lon' => $q->lon,
                'call' => $q->call,
                'wwl' => $q->wwl,
                'points' => $q->points,
                'dist' => $q->dist,
                'azimut' => $q->azimut,
                'mode' => $q->mode->value,
                'time' => $q->timeMinutes,
            ]),
            'squares' => $this->geometry->bigSquares($head),
            'roundStations' => $this->geometry->roundStations($head),
            'roundDataPending' => ! $this->geometry->roundResultsDisclosable($head),
            'porovnaniDostupne' => $this->porovnani->hasRivals($head),
            'cumulative' => $this->geometry->prubehSkore($enriched, $homeSq),
            'timeline' => $this->statistiky->timeline($enriched, $nasobice, $fromMin, $toMin),
            'azimuth' => $this->statistiky->azimuthRose($enriched),
            'squarePoints' => $this->statistiky->bodyPodleCtvercu($enriched),
            'sezona' => $this->statistiky->sezona($head),
            'tempo' => $this->statistiky->tempo($enriched, $fromMin, $toMin),
            'modeStats' => $this->statistiky->modeStats($enriched),
            'odx' => $this->statistiky->topOdx($enriched),
            'nezapocitanaCelkem' => $this->statistiky->nezapocitana($head)['celkem'],
            'distHistogram' => $this->distHistogram($enriched),
            'stats' => $this->stats($enriched),
        ]);
    }

    /**
     * Histogram vzdáleností v km → počty QSO.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array<string, int>
     */
    private function distHistogram(Collection $lines): array
    {
        $buckets = ['0–50' => 0, '50–100' => 0, '100–200' => 0, '200–400' => 0, '400–700' => 0, '700+' => 0];

        foreach ($lines as $l) {
            if ($l->dist === null) {
                continue;
            }

            $d = $l->dist;
            $key = match (true) {
                $d < 50 => '0–50',
                $d < 100 => '50–100',
                $d < 200 => '100–200',
                $d < 400 => '200–400',
                $d < 700 => '400–700',
                default => '700+',
            };
            $buckets[$key]++;
        }

        return $buckets;
    }

    /**
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{pocet: int, maxDist: int, avgDist: int, uniqueSq: int}
     */
    private function stats(Collection $lines): array
    {
        $dists = [];

        foreach ($lines as $l) {
            if ($l->dist !== null) {
                $dists[] = $l->dist;
            }
        }

        $maxDist = $dists !== [] ? max($dists) : 0;
        $avgDist = count($dists) > 0 ? (int) round(array_sum($dists) / count($dists)) : 0;

        $uniqueSq = $lines
            ->map(fn (EnrichedQso $l): string => strtoupper(substr($l->wwl, 0, 4)))
            ->unique()
            ->count();

        return [
            'pocet' => $lines->count(),
            'maxDist' => $maxDist,
            'avgDist' => $avgDist,
            'uniqueSq' => $uniqueSq,
        ];
    }
}
