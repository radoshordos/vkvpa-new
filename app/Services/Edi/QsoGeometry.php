<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\Edihead;
use App\Models\Ediline;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sdílená geometrie spojení pro mapové (MapController) a vizualizační
 * (EdiVizualizaceController) pohledy.
 *
 * Sjednocuje dříve duplikovaný kód: dopočet souřadnic protistanice z lokátoru,
 * vzdálenost/azimut z domácího QTH, body za spojení (z lokátorů, ne z deníku)
 * a agregaci do velkých čtverců. Vždy jen QSO uvnitř závodního okna.
 */
final class QsoGeometry
{
    /**
     * Obohacená QSO v závodním okně (s platnými souřadnicemi).
     *
     * @param  array{lat: float, lon: float}|null  $home  souřadnice domácího QTH
     * @param  string  $orderColumn  sloupec řazení (např. 'Time' nebo 'Received-WWL')
     * @return Collection<int, EnrichedQso>
     */
    public function enrichedQsos(Edihead $head, ?array $home, string $orderColumn = 'Time'): Collection
    {
        $homeSq = strtoupper(substr((string) $head->PWWLo, 0, 4));

        return $head->lines()
            ->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])
            ->orderBy($orderColumn)
            ->get(['lon', 'lat', 'CallSign', 'Received-WWL', 'Time', 'Mode-code'])
            ->map(function (Ediline $l) use ($home, $head, $homeSq): ?EnrichedQso {
                $lat = $l->lat;
                $lon = $l->lon;
                $wwl = $l->receivedWwl();

                // Když chybí lon/lat, dopočítej ze středu lokátoru.
                if (($lat === null || $lon === null) && $wwl !== '') {
                    $c = Maidenhead::toLatLon($wwl);
                    $lat = $c['lat'] ?? null;
                    $lon = $c['lon'] ?? null;
                }

                if ($lat === null || $lon === null) {
                    Log::debug('qso_geometry.skip', [
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

                $time = (string) $l->Time;
                $timeMinutes = (int) substr($time, 0, 2) * 60 + (int) substr($time, 2, 2);

                // Body za spojení přepočítáme z lokátorů (neplatný → 0); sloupec
                // QSO-Points z deníku se ignoruje (shodně se ScoringService).
                $workedSq = strtoupper(substr(trim($wwl), 0, 4));

                return new EnrichedQso(
                    lat: $lat,
                    lon: $lon,
                    call: (string) $l->CallSign,
                    wwl: $wwl,
                    points: Maidenhead::qsoPoints($homeSq, $workedSq),
                    dist: $dist,
                    azimut: $azimut,
                    timeMinutes: $timeMinutes,
                    mode: $l->mode(),
                );
            })
            ->filter()
            ->values();
    }

    /**
     * Agregace protistanic do velkých čtverců (4 znaky lokátoru) se středem.
     *
     * @return Collection<int, BigSquareCount>
     */
    public function bigSquares(Edihead $head): Collection
    {
        $counts = [];

        foreach ($head->lines()->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])->get(['Received-WWL']) as $l) {
            $sq = strtoupper(substr(trim($l->receivedWwl()), 0, 4));
            if (preg_match('/^[A-R]{2}\d{2}$/', $sq) === 1) {
                $counts[$sq] = ($counts[$sq] ?? 0) + 1;
            }
        }

        $out = [];

        foreach ($counts as $sq => $count) {
            $center = Maidenhead::bigSquareCenter((string) $sq);

            if ($center === null) {
                Log::debug('qso_geometry.square.skip', ['edihead_id' => $head->ID, 'square' => $sq]);

                continue;
            }

            $out[] = new BigSquareCount((string) $sq, $count, $center['lat'], $center['lon']);
        }

        return collect($out);
    }
}
