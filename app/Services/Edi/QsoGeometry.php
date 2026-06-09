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
 * Geometrie spojení pro vizualizační pohled ({@see EdiVizualizaceController}).
 *
 * Dopočítává souřadnice protistanice z lokátoru, vzdálenost/azimut z domácího
 * QTH, body za spojení (z lokátorů, ne z deníku) a agregaci do velkých čtverců.
 * Vždy jen QSO uvnitř závodního okna.
 *
 * @phpstan-type CompareStation array{lat: float, lon: float, call: string, wwl: string, dist: int|null}
 */
final class QsoGeometry
{
    /** @var array<int, bool> */
    private array $disclosableCache = [];

    /**
     * Obohacená QSO v závodním okně (s platnými souřadnicemi).
     *
     * @param  array{lat: float, lon: float}|null  $home  souřadnice domácího QTH
     * @param  string  $orderColumn  sloupec řazení (např. 'time' nebo 'received_wwl')
     * @return Collection<int, EnrichedQso>
     */
    public function enrichedQsos(Edihead $head, ?array $home, string $orderColumn = 'time'): Collection
    {
        $homeSq = strtoupper(substr((string) $head->p_wwlo, 0, 4));

        return $head->lines()
            ->whereBetween('time', [ContestWindow::from(), ContestWindow::to()])
            ->orderBy($orderColumn)
            ->get(['lon', 'lat', 'call_sign', 'received_wwl', 'time', 'mode_code'])
            ->map(function (Ediline $l) use ($home, $head, $homeSq): ?EnrichedQso {
                $lat = $l->lat;
                $lon = $l->lon;
                $wwl = $l->receivedWwl;

                // Když chybí lon/lat, dopočítej ze středu lokátoru.
                if (($lat === null || $lon === null) && $wwl !== '') {
                    $c = Maidenhead::toLatLon($wwl);
                    $lat = $c['lat'] ?? null;
                    $lon = $c['lon'] ?? null;
                }

                if ($lat === null || $lon === null) {
                    Log::debug('qso_geometry.skip', [
                        'edihead_id' => $head->id,
                        'call' => (string) $l->call_sign,
                        'wwl' => $wwl,
                    ]);

                    return null;
                }

                $lat = (float) $lat;
                $lon = (float) $lon;
                $dist = $home === null ? null : (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $lat, $lon));
                $azimut = $home === null ? null : (int) round(Maidenhead::bearingDeg($home['lat'], $home['lon'], $lat, $lon));

                $time = (string) $l->time;
                $timeMinutes = (int) substr($time, 0, 2) * 60 + (int) substr($time, 2, 2);

                // Body za spojení přepočítáme z lokátorů (neplatný → 0); sloupec
                // qso_points z deníku se ignoruje (shodně se ScoringService).
                $workedSq = strtoupper(substr(trim($wwl), 0, 4));

                return new EnrichedQso(
                    lat: $lat,
                    lon: $lon,
                    call: (string) $l->call_sign,
                    wwl: $wwl,
                    points: Maidenhead::qsoPoints($homeSq, $workedSq),
                    dist: $dist,
                    azimut: $azimut,
                    timeMinutes: $timeMinutes,
                    mode: $l->mode,
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

        foreach ($head->lines()->whereBetween('time', [ContestWindow::from(), ContestWindow::to()])->get(['received_wwl']) as $l) {
            $sq = strtoupper(substr(trim($l->receivedWwl), 0, 4));
            if (preg_match('/^[A-R]{2}\d{2}$/', $sq) === 1) {
                $counts[$sq] = ($counts[$sq] ?? 0) + 1;
            }
        }

        $out = [];

        foreach ($counts as $sq => $count) {
            $center = Maidenhead::bigSquareCenter((string) $sq);

            if ($center === null) {
                Log::debug('qso_geometry.square.skip', ['edihead_id' => $head->id, 'square' => $sq]);

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
        /** @var array<int, array{lat: float, lon: float, call: string, wwl: string, count: int}> $out */
        $out = [];

        if (! $this->roundResultsDisclosable($head)) {
            return collect($out);
        } elseif ($head->id_kola === null) {
            $headIds = [$head->id];
        } else {
            $headIds = Edihead::query()->where('id_kola', $head->id_kola)->pluck('id')->all();
        }

        /** @var array<string, array{count: int, lat: float|null, lon: float|null, wwl: string}> $stations */
        $stations = [];

        foreach (
            Ediline::query()
                ->whereIn('edihead_id', $headIds)
                ->whereBetween('time', [ContestWindow::from(), ContestWindow::to()])
                ->orderBy('time')
                ->get(['call_sign', 'received_wwl', 'lon', 'lat']) as $l
        ) {
            $call = strtoupper(trim((string) $l->call_sign));
            if ($call === '') {
                continue;
            }

            $wwl = $l->receivedWwl;
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
     * Porovnání dvou deníků téhož kola: které protistanice udělal jen tento
     * deník, které jen soupeř a které oba (obdoba „stations worked by X, but
     * not by Y" na vushf.dk). Stanice se párují podle značky; souřadnice,
     * lokátor a vzdálenost se berou z prvního výskytu v daném deníku,
     * vzdálenost vždy od domácího QTH tohoto deníku ($home). Značky obou
     * porovnávaných stanic se vynechávají – vzájemné spojení není „náskok".
     *
     * Férovost jako u {@see roundStations()}: dokud kolo není uzavřené nebo
     * vyhodnocené, cizí deník se nesmí odhalit a metoda vrací null. Deníky
     * musí patřit do téhož (ne-null) kola.
     *
     * @param  array{lat: float, lon: float}|null  $home  souřadnice domácího QTH tohoto deníku
     * @return array{onlyMine: list<CompareStation>, onlyRival: list<CompareStation>, both: list<CompareStation>}|null
     */
    public function compareWith(Edihead $head, Edihead $rival, ?array $home): ?array
    {
        if ($head->id_kola === null || $head->id_kola !== $rival->id_kola || $head->id === $rival->id) {
            return null;
        }

        if (! $this->roundResultsDisclosable($head)) {
            return null;
        }

        $skipCalls = [
            strtoupper(trim($head->p_call)),
            strtoupper(trim($rival->p_call)),
        ];

        $mine = $this->stationsByCall($head, $home, $skipCalls);
        $theirs = $this->stationsByCall($rival, $home, $skipCalls);

        return [
            'onlyMine' => array_values(array_diff_key($mine, $theirs)),
            'onlyRival' => array_values(array_diff_key($theirs, $mine)),
            'both' => array_values(array_intersect_key($mine, $theirs)),
        ];
    }

    /**
     * Protistanice deníku (v závodním okně) klíčované normalizovanou značkou.
     * Souřadnice/lokátor/vzdálenost z prvního výskytu, vzdálenost od $home.
     *
     * @param  array{lat: float, lon: float}|null  $home
     * @param  list<string>  $skipCalls  značky, které se vynechají
     * @return array<string, CompareStation>
     */
    private function stationsByCall(Edihead $head, ?array $home, array $skipCalls): array
    {
        $out = [];

        foreach ($this->enrichedQsos($head, $home) as $q) {
            $call = strtoupper(trim($q->call));

            if ($call === '' || in_array($call, $skipCalls, true) || isset($out[$call])) {
                continue;
            }

            $out[$call] = [
                'lat' => $q->lat,
                'lon' => $q->lon,
                'call' => $call,
                'wwl' => $q->wwl,
                'dist' => $q->dist,
            ];
        }

        return $out;
    }

    /**
     * Smí se vrstva „všechny stanice z kola" zveřejnit?
     *
     * Bez kola (`id_kola === null`) jde jen o vlastní deník – žádná cizí data,
     * lze zobrazit. S kolem se cizí stanice odhalí až po uzávěrce nebo
     * vyhodnocení; během příjmu hlášení (a u nenalezeného kola) se skryjí.
     *
     * Výsledek je memoizován per `id_kola` pro trvání requestu – volání
     * `roundStations()` a dotaz controlleru na stav tak sdílí jeden SELECT.
     */
    public function roundResultsDisclosable(Edihead $head): bool
    {
        $key = $head->id_kola ?? -1;

        if (! array_key_exists($key, $this->disclosableCache)) {
            if ($head->id_kola === null) {
                $this->disclosableCache[$key] = true;
            } else {
                $kolo = VkvpaKola::query()->find($head->id_kola);
                $this->disclosableCache[$key] = $kolo !== null
                    && in_array($kolo->stav(), [KoloStav::Uzavrene, KoloStav::Vyhodnocene], true);
            }
        }

        return $this->disclosableCache[$key];
    }
}
