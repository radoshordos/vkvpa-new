<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Models\Ediline;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * @phpstan-type EnrichedLine array{lat: float, lon: float, call: string, wwl: string, points: int, dist: int|null, azimut: int|null, timeMinutes: int, mode: int}
 * @phpstan-type MapPoint array{lat: float, lon: float, call: string, wwl: string, points: int, dist: int|null, azimut: int|null, mode: int}
 * @phpstan-type Square array{square: string, count: int, lat: float, lon: float}
 */
class EdiVizualizaceController extends Controller
{
    public function show(Edihead $head): View
    {
        $home = Maidenhead::toLatLon((string) $head->PWWLo);
        $homeSq = strtoupper(substr((string) $head->PWWLo, 0, 4));

        $enriched = $this->enrichedLines($head, $home, $homeSq);

        return view('pages.vizualizace', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->PCall,
            'homeLoc' => (string) $head->PWWLo,
            'home' => $home,
            'mapPoints' => $enriched->map(fn (array $l): array => [
                'lat' => $l['lat'], 'lon' => $l['lon'],
                'call' => $l['call'], 'wwl' => $l['wwl'],
                'points' => $l['points'], 'dist' => $l['dist'], 'azimut' => $l['azimut'],
                'mode' => $l['mode'],
            ]),
            'squares' => $this->squares($head),
            'timeline' => $this->timeline($enriched),
            'azimuth' => $this->azimuthRose($enriched),
            'distHistogram' => $this->distHistogram($enriched),
            'stats' => $this->stats($enriched),
        ]);
    }

    /**
     * @param  array{lat: float, lon: float}|null  $home
     * @return Collection<int, EnrichedLine>
     */
    private function enrichedLines(Edihead $head, ?array $home, string $homeSq): Collection
    {
        return $head->lines()
            ->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])
            ->orderBy('Time')
            ->get(['lon', 'lat', 'CallSign', 'Received-WWL', 'QSO-Points', 'Time', 'Mode-code'])
            ->map(function (Ediline $l) use ($home, $head, $homeSq): ?array {
                $lat = $l->lat;
                $lon = $l->lon;
                $wwl = $l->receivedWwl();

                if (($lat === null || $lon === null) && $wwl !== '') {
                    $c = Maidenhead::toLatLon($wwl);
                    $lat = $c['lat'] ?? null;
                    $lon = $c['lon'] ?? null;
                }

                if ($lat === null || $lon === null) {
                    Log::debug('vizualizace.skip', ['edihead_id' => $head->ID, 'call' => (string) $l->CallSign]);

                    return null;
                }

                $lat = (float) $lat;
                $lon = (float) $lon;
                $dist = $home === null ? null : (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $lat, $lon));
                $azimut = $home === null ? null : (int) round(Maidenhead::bearingDeg($home['lat'], $home['lon'], $lat, $lon));
                $time = (string) $l->Time;
                $timeMinutes = (int) substr($time, 0, 2) * 60 + (int) substr($time, 2, 2);
                $workedSq = strtoupper(substr(trim($wwl), 0, 4));
                $points = preg_match('/^[A-R]{2}\d{2}$/', $homeSq) !== false
                    && preg_match('/^[A-R]{2}\d{2}$/', $workedSq) !== false
                    ? Maidenhead::qsoPoints($homeSq, $workedSq)
                    : $l->qsoPoints();

                return [
                    'lat' => $lat, 'lon' => $lon,
                    'call' => (string) $l->CallSign,
                    'wwl' => $wwl,
                    'points' => $points,
                    'dist' => $dist,
                    'azimut' => $azimut,
                    'timeMinutes' => $timeMinutes,
                    'mode' => (int) $l->{'Mode-code'}, // 1=SSB, 2=CW
                ];
            })
            ->filter()
            ->values();
    }

    /** @return Collection<int, Square> */
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
                continue;
            }

            $out[] = ['square' => (string) $sq, 'count' => $count, 'lat' => $center['lat'], 'lon' => $center['lon']];
        }

        return collect($out);
    }

    /**
     * 15-minutové intervaly 08:00–11:00 → počty QSO.
     *
     * @param  Collection<int, EnrichedLine>  $lines
     * @return array<string, int>
     */
    private function timeline(Collection $lines): array
    {
        $buckets = [];

        for ($min = 480; $min < 660; $min += 15) {
            $buckets[sprintf('%02d:%02d', intdiv($min, 60), $min % 60)] = 0;
        }

        foreach ($lines as $l) {
            $slot = intdiv($l['timeMinutes'] - 480, 15) * 15 + 480;

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
     * @param  Collection<int, EnrichedLine>  $lines
     * @return array{labels: array<string>, data: array<int, int>}
     */
    private function azimuthRose(Collection $lines): array
    {
        $labels = ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'];
        /** @var array<int, int> $counts */
        $counts = array_fill(0, 8, 0);

        foreach ($lines as $l) {
            if ($l['azimut'] === null) {
                continue;
            }
            $sector = (int) (($l['azimut'] + 22.5) / 45) % 8;
            $counts[$sector]++;
        }

        return ['labels' => $labels, 'data' => $counts];
    }

    /**
     * Histogram vzdáleností v km → počty QSO.
     *
     * @param  Collection<int, EnrichedLine>  $lines
     * @return array<string, int>
     */
    private function distHistogram(Collection $lines): array
    {
        $buckets = ['0–50' => 0, '50–100' => 0, '100–200' => 0, '200–400' => 0, '400–700' => 0, '700+' => 0];

        foreach ($lines as $l) {
            if ($l['dist'] === null) {
                continue;
            }

            $d = $l['dist'];
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
     * @param  Collection<int, EnrichedLine>  $lines
     * @return array{pocet: int, maxDist: int, avgDist: int, uniqueSq: int}
     */
    private function stats(Collection $lines): array
    {
        // Iterate directly so PHPStan can track EnrichedLine field types without pluck().
        $dists = [];

        foreach ($lines as $l) {
            if ($l['dist'] !== null) {
                $dists[] = $l['dist'];
            }
        }

        $maxDist = array_reduce($dists, fn (int $carry, int $d): int => max($carry, $d), 0);
        $avgDist = count($dists) > 0 ? (int) round(array_sum($dists) / count($dists)) : 0;

        $uniqueSq = $lines
            ->map(fn (array $l): string => strtoupper(substr($l['wwl'], 0, 4)))
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
