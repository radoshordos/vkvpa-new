<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\QsoGeometry;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Komplexní vizualizace deníku: mapa + grafy na jedné stránce. Geometrii spojení
 * (souřadnice, vzdálenost, azimut, body, čtverce) počítá sdílená {@see QsoGeometry};
 * tento controller z ní jen odvozuje agregace pro grafy.
 */
class EdiVizualizaceController extends Controller
{
    public function __construct(private readonly QsoGeometry $geometry) {}

    public function show(Edihead $head): View
    {
        $home = Maidenhead::toLatLon((string) $head->PWWLo);

        $enriched = $this->geometry->enrichedQsos($head, $home, 'Time');

        return view('pages.vizualizace', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'mapPoints' => $enriched->map(fn (EnrichedQso $q): array => [
                'lat' => $q->lat,
                'lon' => $q->lon,
                'call' => $q->call,
                'wwl' => $q->wwl,
                'points' => $q->points,
                'dist' => $q->dist,
                'azimut' => $q->azimut,
                'mode' => $q->mode->value,
            ]),
            'squares' => $this->geometry->bigSquares($head),
            'roundStations' => $this->geometry->roundStations($head),
            'roundDataPending' => $this->geometry->roundDataPending($head),
            'timeline' => $this->timeline($enriched),
            'azimuth' => $this->azimuthRose($enriched),
            'distHistogram' => $this->distHistogram($enriched),
            'stats' => $this->stats($enriched),
        ]);
    }

    /**
     * 15-minutové intervaly 08:00–11:00 → počty QSO.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array<string, int>
     */
    private function timeline(Collection $lines): array
    {
        $buckets = [];

        for ($min = 480; $min < 660; $min += 15) {
            $buckets[sprintf('%02d:%02d', intdiv($min, 60), $min % 60)] = 0;
        }

        foreach ($lines as $l) {
            $slot = intdiv($l->timeMinutes - 480, 15) * 15 + 480;

            if ($slot >= 480 && $slot < 660) {
                $key = sprintf('%02d:%02d', intdiv($slot, 60), $slot % 60);
                $buckets[$key]++;
            }
        }

        return $buckets;
    }

    /**
     * 8 světových stran (45° sektorů) po směru hodinových ručiček od severu → počty QSO.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{labels: array<string>, data: array<int, int>}
     */
    private function azimuthRose(Collection $lines): array
    {
        $labels = ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'];
        /** @var array<int, int> $counts */
        $counts = array_fill(0, 8, 0);

        foreach ($lines as $l) {
            if ($l->azimut === null) {
                continue;
            }
            $sector = (int) (($l->azimut + 22.5) / 45) % 8;
            $counts[$sector]++;
        }

        return ['labels' => $labels, 'data' => $counts];
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
