<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Enums\KoloStav;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaKola;
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

    /**
     * Stanice z celého kola s alespoň $minQso spojeními – vrstva „všechny
     * stanice z kola" kombinované mapy (obdoba „show all logged calls" na
     * vkvzavody.crk.cz).
     *
     * Agreguje protistanice napříč všemi deníky téhož kola (`id_kola`); když
     * deník kolo nemá (null), počítá jen z něj. Každá značka je zastoupena
     * jednou, se souřadnicemi a lokátorem prvního platného výskytu a s počtem
     * spojení napříč všemi deníky kola. Jen QSO uvnitř závodního okna.
     *
     * Pozor – fér­ovost: cizí stanice z kola se zveřejní až po uzávěrce, resp.
     * vyhodnocení kola ({@see KoloStav::Uzavrene}/{@see KoloStav::Vyhodnocene}).
     * Mapa i vizualizace jsou veřejné a běžný účastník je vidí hned po uploadu;
     * během příjmu hlášení by tato vrstva odhalovala deníky soupeřů, proto se
     * v tom případě vrací prázdná kolekce (vlastní deník bez kola se zobrazí).
     *
     * @return Collection<int, array{lat: float, lon: float, call: string, wwl: string, count: int}>
     */
    public function roundStations(Edihead $head, int $minQso = 5): Collection
    {
        if (! $this->roundResultsDisclosable($head)) {
            $headIds = [];
        } elseif ($head->id_kola === null) {
            $headIds = [$head->ID];
        } else {
            $headIds = Edihead::query()->where('id_kola', $head->id_kola)->pluck('ID')->all();
        }

        /** @var array<string, array{count: int, lat: float|null, lon: float|null, wwl: string}> $stations */
        $stations = [];

        foreach (
            Ediline::query()
                ->whereIn('IDS', $headIds)
                ->whereBetween('Time', [ContestWindow::from(), ContestWindow::to()])
                ->orderBy('Time')
                ->get(['CallSign', 'Received-WWL', 'lon', 'lat']) as $l
        ) {
            $call = strtoupper(trim((string) $l->CallSign));
            if ($call === '') {
                continue;
            }

            $wwl = $l->receivedWwl();
            $lat = $l->lat;
            $lon = $l->lon;

            if (($lat === null || $lon === null) && $wwl !== '') {
                $c = Maidenhead::toLatLon($wwl);
                $lat = $c['lat'] ?? null;
                $lon = $c['lon'] ?? null;
            }

            if (! isset($stations[$call])) {
                $stations[$call] = ['count' => 0, 'lat' => null, 'lon' => null, 'wwl' => ''];
            }

            $stations[$call]['count']++;

            // Souřadnice a lokátor z prvního platného výskytu.
            if (($stations[$call]['lat'] === null || $stations[$call]['lon'] === null) && $lat !== null && $lon !== null) {
                $stations[$call]['lat'] = (float) $lat;
                $stations[$call]['lon'] = (float) $lon;
                $stations[$call]['wwl'] = $wwl;
            }
        }

        $out = [];

        foreach ($stations as $call => $s) {
            if ($s['count'] < $minQso || $s['lat'] === null || $s['lon'] === null) {
                continue;
            }

            $out[] = [
                'lat' => $s['lat'],
                'lon' => $s['lon'],
                'call' => $call,
                'wwl' => $s['wwl'],
                'count' => $s['count'],
            ];
        }

        return collect($out);
    }

    /**
     * Čekají na zveřejnění ještě další mapová data? True, dokud kolo deníku
     * není uzavřené/vyhodnocené – teprve pak mapy obsahují i vrstvu „všechny
     * stanice z kola". Podklad pro upozornění v popisku map.
     */
    public function roundDataPending(Edihead $head): bool
    {
        return ! $this->roundResultsDisclosable($head);
    }

    /**
     * Smí se vrstva „všechny stanice z kola" zveřejnit?
     *
     * Bez kola (`id_kola === null`) jde jen o vlastní deník – žádná cizí data,
     * lze zobrazit. S kolem se cizí stanice odhalí až po uzávěrce nebo
     * vyhodnocení; během příjmu hlášení (a u nenalezeného kola) se skryjí.
     */
    private function roundResultsDisclosable(Edihead $head): bool
    {
        if ($head->id_kola === null) {
            return true;
        }

        $kolo = VkvpaKola::query()->find($head->id_kola);

        return $kolo !== null
            && in_array($kolo->stav(), [KoloStav::Uzavrene, KoloStav::Vyhodnocene], true);
    }
}
