<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\RekordyService;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use App\Support\VkvpaSettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Souhrnné statistiky celého (vyhodnoceného) kola pro veřejnou stránku
 * Statistiky. Agreguje napříč všemi deníky kola (`edihead`/`edilines`)
 * a převzatými záznamy výsledkové listiny (`vkvpa_data`):
 *  - souhrn (stanice, QSO, body, čtverce, ODX),
 *  - mapová data (stanice kola, obsazené čtverce, účastníci),
 *  - časová osa aktivity, druhy provozu, země/prefixy, kategorie,
 *  - TOP žebříčky, trend vůči minulým kolům, zajímavosti a all-time odznaky.
 *
 * Geometrii stanic dodává {@see QsoGeometry}, all-time rekordy {@see RekordyService}.
 *
 * @phpstan-type StatStanice array{lat: float, lon: float, call: string, wwl: string, count: int}
 * @phpstan-type StatCtverec array{square: string, count: int, lat: float, lon: float}
 * @phpstan-type StatUcastnik array{lat: float, lon: float, call: string, loc: string, kat: string, body: int, poradi: int}
 * @phpstan-type StatTop array{znacka: string, kategorie: string, body: int, pocet: int, nasobice: int}
 * @phpstan-type StatOdx array{call: string, wwl: string, home: string, homeCall: string, dist: int}
 * @phpstan-type StatNazevPocet array{nazev: string, pocet: int}
 * @phpstan-type StatKat array{zkratka: string, nazev: string, pocet: int}
 * @phpstan-type StatTimeline array{labels: list<string>, counts: list<int>}
 * @phpstan-type StatMody array{ssb: int, cw: int, other: int}
 * @phpstan-type StatTrend array{labels: list<string>, stanic: list<int>, qso: list<int>, body: list<int>}
 * @phpstan-type StatFakt array{key: string, params: array<string, string|int>}
 * @phpstan-type StatOdznaky array{ucast: bool, skore: bool, qso: bool, nasobice: bool}
 * @phpstan-type StatTok array{from: string, to: string, fromLat: float, fromLon: float, toLat: float, toLon: float, count: int}
 * @phpstan-type StatAnalyza array{ctverce: list<StatCtverec>, odx: StatOdx|null, timeline: StatTimeline, mody: StatMody, zeme: list<StatNazevPocet>, prefixy: list<StatNazevPocet>, tok: list<StatTok>}
 * @phpstan-type StatPrehled array{
 *     pocetStanic: int, pocetZaznamu: int, pocetQso: int, bodyCelkem: int,
 *     pocetCtvercu: int, odx: StatOdx|null,
 *     stanice: list<StatStanice>, ctverce: list<StatCtverec>, ucastnici: list<StatUcastnik>,
 *     timeline: StatTimeline, mody: StatMody, zeme: list<StatNazevPocet>, prefixy: list<StatNazevPocet>,
 *     kategorie: list<StatKat>, tok: list<StatTok>,
 *     topBody: list<StatTop>, topQso: list<StatTop>, topNasobice: list<StatTop>,
 *     trend: StatTrend, zajimavosti: list<StatFakt>, odznaky: StatOdznaky
 * }
 */
final class KoloStatistiky
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly RekordyService $rekordy,
    ) {}

    /**
     * Celý přehled kola jako pole (cachuje se – `cache.serializable_classes`
     * je false, proto jen pole/skaláry). Data vyhodnoceného kola se prakticky
     * nemění, takže stačí TTL bez cílené invalidace (shodně s
     * {@see QsoGeometry::roundStations()}).
     *
     * @return StatPrehled
     */
    public function prehled(VkvpaKola $kolo): array
    {
        /** @var StatPrehled $data */
        $data = Cache::remember(
            sprintf('vkvpa:kolo-stats:v3:%d', $kolo->id),
            VkvpaSettings::roundStationsCacheTtl(),
            fn (): array => $this->compute($kolo),
        );

        return $data;
    }

    /**
     * @return StatPrehled
     */
    private function compute(VkvpaKola $kolo): array
    {
        $a = $this->analyzaQso($kolo->id);
        [$souhrn, $kategorie, $topBody, $topQso, $topNasobice] = $this->vysledky($kolo->id);

        return [
            'pocetStanic' => $souhrn['pocetStanic'],
            'pocetZaznamu' => $souhrn['pocetZaznamu'],
            'pocetQso' => $souhrn['pocetQso'],
            'bodyCelkem' => $souhrn['bodyCelkem'],
            'pocetCtvercu' => count($a['ctverce']),
            'odx' => $a['odx'],
            'stanice' => $this->geometry->stationsForKolo($kolo->id),
            'ctverce' => $a['ctverce'],
            'ucastnici' => $this->ucastnici($kolo->id),
            'timeline' => $a['timeline'],
            'mody' => $a['mody'],
            'zeme' => $a['zeme'],
            'prefixy' => $a['prefixy'],
            'kategorie' => $kategorie,
            'tok' => $a['tok'],
            'topBody' => $topBody,
            'topQso' => $topQso,
            'topNasobice' => $topNasobice,
            'trend' => $this->trend($kolo),
            'zajimavosti' => $this->zajimavosti($kolo, $a['ctverce'], $topBody),
            'odznaky' => $this->rekordy->odznakyProKolo($kolo->id),
        ];
    }

    /**
     * Jeden průchod QSO všech deníků kola (v závodním okně): obsazené čtverce,
     * ODX, časová osa aktivity, druhy provozu a rozpad podle zemí/prefixů.
     *
     * @return StatAnalyza
     */
    private function analyzaQso(int $koloId): array
    {
        $fromMin = DenikStatistiky::minutes(ContestWindow::from());
        $toMin = DenikStatistiky::minutes(ContestWindow::to());
        $buckets = max(1, (int) ceil(($toMin - $fromMin) / 15));

        $resolver = PrefixResolver::fromDatabase();

        /** @var array<string, int> $counts */
        $counts = [];
        /** @var array<string, int> $zemeCount */
        $zemeCount = [];
        /** @var array<string, int> $prefixCount */
        $prefixCount = [];
        /** @var array<string, int> $tokCount */
        $tokCount = [];
        $timeline = array_fill(0, $buckets, 0);
        $mody = ['ssb' => 0, 'cw' => 0, 'other' => 0];
        /** @var StatOdx|null $odx */
        $odx = null;

        $rows = DB::table('edilines')
            ->join('edihead', 'edilines.edihead_id', '=', 'edihead.id')
            ->where('edihead.id_kola', $koloId)
            ->whereTime('edilines.qso_at', '>=', ContestWindow::fromSqlTime())
            ->whereTime('edilines.qso_at', '<=', ContestWindow::toSqlTime())
            ->get([
                'edilines.received_wwl as wwl',
                'edilines.lat as lat',
                'edilines.lon as lon',
                'edilines.call_sign as call_sign',
                'edilines.mode_code as mode_code',
                'edilines.qso_at as qso_at',
                'edihead.p_wwlo as home_wwl',
                'edihead.p_call as home_call',
            ]);

        foreach ($rows as $r) {
            $wwl = strtoupper(trim(is_scalar($r->wwl) ? (string) $r->wwl : ''));
            $sq = Maidenhead::bigSquare($wwl);
            if (Maidenhead::isValidBigSquare($sq)) {
                $counts[$sq] = ($counts[$sq] ?? 0) + 1;
            }

            // Druh provozu: 1 = SSB, 2 = CW, jiné = ostatní.
            $mode = is_numeric($r->mode_code) ? (int) $r->mode_code : 0;
            if ($mode === 1) {
                $mody['ssb']++;
            } elseif ($mode === 2) {
                $mody['cw']++;
            } else {
                $mody['other']++;
            }

            // Časová osa: 15min interval závodního okna.
            $min = self::minutesFromDateTime(is_scalar($r->qso_at) ? (string) $r->qso_at : '');
            if ($min !== null) {
                $i = $min === $toMin ? $buckets - 1 : intdiv($min - $fromMin, 15);
                if ($i >= 0 && $i < $buckets) {
                    $timeline[$i]++;
                }
            }

            // Země / prefix protistanice (neznámé → '' = koš „Ostatní").
            $call = strtoupper(trim(is_scalar($r->call_sign) ? (string) $r->call_sign : ''));
            $lk = $call !== '' ? $resolver->lookup($call) : null;
            $zKey = $lk['country'] ?? '';
            $pKey = $lk['prefix'] ?? '';
            $zemeCount[$zKey] = ($zemeCount[$zKey] ?? 0) + 1;
            $prefixCount[$pKey] = ($prefixCount[$pKey] ?? 0) + 1;

            // Tok mezi velkými čtverci (pavučina): domácí čtverec → pracovaný.
            $homeWwl = strtoupper(trim(is_scalar($r->home_wwl) ? (string) $r->home_wwl : ''));
            $homeSq = Maidenhead::bigSquare($homeWwl);
            if (Maidenhead::isValidBigSquare($homeSq) && Maidenhead::isValidBigSquare($sq) && $homeSq !== $sq) {
                $tk = $homeSq.'|'.$sq;
                $tokCount[$tk] = ($tokCount[$tk] ?? 0) + 1;
            }

            // ODX kola.
            $worked = $this->coords($wwl, $r->lat, $r->lon);
            $home = $this->coords($homeWwl, null, null);
            if ($worked === null || $home === null) {
                continue;
            }
            $dist = (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $worked['lat'], $worked['lon']));
            if ($odx === null || $dist > $odx['dist']) {
                $odx = [
                    'call' => $call,
                    'wwl' => $wwl,
                    'home' => $homeWwl,
                    'homeCall' => strtoupper(trim(is_scalar($r->home_call) ? (string) $r->home_call : '')),
                    'dist' => $dist,
                ];
            }
        }

        /** @var list<StatCtverec> $ctverce */
        $ctverce = [];
        foreach ($counts as $sq => $count) {
            $center = Maidenhead::bigSquareCenter((string) $sq);
            if ($center === null) {
                continue;
            }
            $ctverce[] = ['square' => (string) $sq, 'count' => $count, 'lat' => $center['lat'], 'lon' => $center['lon']];
        }
        usort($ctverce, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        /** @var list<StatTok> $tok */
        $tok = [];
        foreach ($tokCount as $pair => $count) {
            $parts = explode('|', (string) $pair, 2);
            $from = $parts[0];
            $to = $parts[1] ?? '';
            $cf = Maidenhead::bigSquareCenter($from);
            $ct = Maidenhead::bigSquareCenter($to);
            if ($cf === null || $ct === null) {
                continue;
            }
            $tok[] = ['from' => $from, 'to' => $to, 'fromLat' => $cf['lat'], 'fromLon' => $cf['lon'], 'toLat' => $ct['lat'], 'toLon' => $ct['lon'], 'count' => $count];
        }
        usort($tok, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $tok = array_slice($tok, 0, 150);

        $labels = [];
        for ($i = 0; $i < $buckets; $i++) {
            $labels[] = DenikStatistiky::hhmm($fromMin + $i * 15);
        }

        return [
            'ctverce' => $ctverce,
            'odx' => $odx,
            'timeline' => ['labels' => $labels, 'counts' => array_values($timeline)],
            'mody' => $mody,
            'zeme' => $this->topNazevPocet($zemeCount),
            'prefixy' => $this->topNazevPocet($prefixCount),
            'tok' => $tok,
        ];
    }

    /**
     * Souhrnná čísla, kategorie a TOP žebříčky z převzatých záznamů listiny.
     *
     * @return array{
     *     0: array{pocetStanic: int, pocetZaznamu: int, pocetQso: int, bodyCelkem: int},
     *     1: list<StatKat>, 2: list<StatTop>, 3: list<StatTop>, 4: list<StatTop>
     * }
     */
    private function vysledky(int $koloId): array
    {
        /** @var SupportCollection<int, string> $zkratky */
        $zkratky = VkvpaKategorie::query()->pluck('zkratka', 'id');

        $entries = VkvpaData::query()
            ->where('id_kola', $koloId)
            ->approved()
            ->get(['znacka', 'body', 'pocet', 'nasobice', 'id_kategorie']);

        $souhrn = [
            'pocetStanic' => $entries->pluck('znacka')->unique()->count(),
            'pocetZaznamu' => $entries->count(),
            'pocetQso' => (int) $entries->sum(fn (VkvpaData $e): int => $e->pocet),
            'bodyCelkem' => (int) $entries->sum(fn (VkvpaData $e): int => $e->body),
        ];

        return [
            $souhrn,
            $this->kategorie($koloId),
            $this->topN($entries, $zkratky, 'body'),
            $this->topN($entries, $zkratky, 'pocet'),
            $this->topN($entries, $zkratky, 'nasobice'),
        ];
    }

    /**
     * Rozdělení převzatých záznamů do kategorií (sestupně podle počtu).
     *
     * @return list<StatKat>
     */
    private function kategorie(int $koloId): array
    {
        $out = [];
        $rows = VkvpaData::query()
            ->leftJoin('vkvpa_kategorie', 'vkvpa_data.id_kategorie', '=', 'vkvpa_kategorie.id')
            ->where('vkvpa_data.id_kola', $koloId)
            ->where('vkvpa_data.schvaleno', true)
            ->groupBy('vkvpa_data.id_kategorie', 'vkvpa_kategorie.zkratka', 'vkvpa_kategorie.nazev')
            ->selectRaw('vkvpa_kategorie.zkratka as zkratka, vkvpa_kategorie.nazev as nazev, COUNT(*) as pocet')
            ->get();

        foreach ($rows as $row) {
            $out[] = [
                'zkratka' => self::strAttr($row, 'zkratka') ?: '?',
                'nazev' => self::strAttr($row, 'nazev') ?: '?',
                'pocet' => self::intAttr($row, 'pocet'),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['pocet'] <=> $a['pocet']);

        return $out;
    }

    /**
     * Účastníci kola na mapě: domácí QTH z lokátoru záznamu listiny.
     *
     * @return list<StatUcastnik>
     */
    private function ucastnici(int $koloId): array
    {
        $zkratky = VkvpaKategorie::query()->pluck('zkratka', 'id');

        $out = [];
        $entries = VkvpaData::query()
            ->where('id_kola', $koloId)
            ->approved()
            ->orderByDesc('body')
            ->get(['znacka', 'locator', 'body', 'poradi', 'id_kategorie']);

        foreach ($entries as $e) {
            $loc = strtoupper(trim($e->locator));
            $c = Maidenhead::toLatLon($loc) ?? Maidenhead::bigSquareCenter(Maidenhead::bigSquare($loc));
            if ($c === null) {
                continue;
            }
            $zkr = $e->id_kategorie !== null ? $zkratky->get($e->id_kategorie) : null;
            $out[] = [
                'lat' => $c['lat'],
                'lon' => $c['lon'],
                'call' => (string) $e->znacka,
                'loc' => $loc,
                'kat' => is_string($zkr) ? $zkr : '',
                'body' => (int) $e->body,
                'poradi' => (int) $e->poradi,
            ];
        }

        return $out;
    }

    /**
     * Trend posledních $n vyhodnocených kol (včetně tohoto) – účast, QSO, body.
     *
     * @return StatTrend
     */
    private function trend(VkvpaKola $kolo, int $n = 12): array
    {
        $kola = VkvpaKola::query()
            ->whereNotNull('vyhodnoceno')
            ->where('datum_konani', '<=', $kolo->datum_konani)
            ->orderByDesc('datum_konani')
            ->limit($n)
            ->get(['id', 'nazev', 'datum_konani'])
            ->sortBy('datum_konani')
            ->values();

        $agg = VkvpaData::query()
            ->where('schvaleno', true)
            ->whereIn('id_kola', $kola->pluck('id'))
            ->selectRaw('id_kola, COUNT(DISTINCT znacka) as stanic, SUM(pocet) as qso, SUM(body) as body')
            ->groupBy('id_kola')
            ->get()
            ->keyBy('id_kola');

        $labels = [];
        $stanic = [];
        $qso = [];
        $body = [];
        foreach ($kola as $k) {
            $a = $agg->get($k->id);
            $labels[] = (string) $k->nazev;
            $stanic[] = $a !== null ? self::intAttr($a, 'stanic') : 0;
            $qso[] = $a !== null ? self::intAttr($a, 'qso') : 0;
            $body[] = $a !== null ? self::intAttr($a, 'body') : 0;
        }

        return ['labels' => $labels, 'stanic' => $stanic, 'qso' => $qso, 'body' => $body];
    }

    /**
     * Automatické „zajímavosti" (texty se skládají v šabloně přes překlad,
     * aby cache nebyla závislá na jazyce).
     *
     * @param  list<StatCtverec>  $ctverce
     * @param  list<StatTop>  $topBody
     * @return list<StatFakt>
     */
    private function zajimavosti(VkvpaKola $kolo, array $ctverce, array $topBody): array
    {
        $out = [];

        if ($ctverce !== []) {
            $out[] = ['key' => 'fact_active_square', 'params' => ['square' => $ctverce[0]['square'], 'count' => $ctverce[0]['count']]];
        }

        if (isset($topBody[0], $topBody[1])) {
            $out[] = ['key' => 'fact_winner_margin', 'params' => [
                'call' => $topBody[0]['znacka'],
                'margin' => $topBody[0]['body'] - $topBody[1]['body'],
            ]];
        }

        $novacku = $this->novacku($kolo);
        if ($novacku > 0) {
            $out[] = ['key' => 'fact_debutants', 'params' => ['count' => $novacku]];
        }

        return $out;
    }

    /** Počet značek, které v tomto kole startovaly poprvé (nebyly v dřívějších kolech). */
    private function novacku(VkvpaKola $kolo): int
    {
        $earlier = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->where('vkvpa_kola.datum_konani', '<', $kolo->datum_konani)
            ->distinct()
            ->pluck('vkvpa_data.znacka');

        return VkvpaData::query()
            ->where('id_kola', $kolo->id)
            ->approved()
            ->whereNotIn('znacka', $earlier)
            ->distinct()
            ->count('znacka');
    }

    /**
     * TOP 10 záznamů sestupně podle sloupce ($col = body|pocet|nasobice).
     *
     * @param  EloquentCollection<int, VkvpaData>  $entries
     * @param  SupportCollection<int, string>  $zkratky  id kategorie → zkratka
     * @return list<StatTop>
     */
    private function topN(EloquentCollection $entries, SupportCollection $zkratky, string $col): array
    {
        $sorted = $entries
            ->sortByDesc(fn (VkvpaData $e): int => match ($col) {
                'pocet' => $e->pocet,
                'nasobice' => $e->nasobice,
                default => $e->body,
            })
            ->take(10);

        $out = [];
        foreach ($sorted as $e) {
            $zkr = $e->id_kategorie !== null ? $zkratky->get($e->id_kategorie) : null;
            $out[] = [
                'znacka' => (string) $e->znacka,
                'kategorie' => is_string($zkr) ? $zkr : '',
                'body' => (int) $e->body,
                'pocet' => (int) $e->pocet,
                'nasobice' => (int) $e->nasobice,
            ];
        }

        return $out;
    }

    /**
     * Pole „klíč → počet" → seřazený seznam TOP 12 {nazev, pocet}.
     *
     * @param  array<string, int>  $counts
     * @return list<StatNazevPocet>
     */
    private function topNazevPocet(array $counts): array
    {
        $out = [];
        foreach ($counts as $nazev => $pocet) {
            $out[] = ['nazev' => (string) $nazev, 'pocet' => $pocet];
        }
        usort($out, static fn (array $a, array $b): int => $b['pocet'] <=> $a['pocet'] ?: strcmp($a['nazev'], $b['nazev']));

        return array_slice($out, 0, 12);
    }

    /**
     * Souřadnice protistanice / QTH: přednostně z uložených lon/lat, jinak ze
     * středu (sub)čtverce lokátoru.
     *
     * @return array{lat: float, lon: float}|null
     */
    private function coords(string $wwl, mixed $lat, mixed $lon): ?array
    {
        // Uložené lat/lon použijeme jen v platném rozsahu (jinak by phpgeo
        // Coordinate v distanceKm vyhodilo výjimku); jinak střed lokátoru.
        if (is_numeric($lat) && is_numeric($lon)) {
            $la = (float) $lat;
            $lo = (float) $lon;
            if ($la >= -90.0 && $la <= 90.0 && $lo >= -180.0 && $lo <= 180.0) {
                return ['lat' => $la, 'lon' => $lo];
            }
        }

        return Maidenhead::toLatLon($wwl) ?? Maidenhead::bigSquareCenter($wwl);
    }

    /** Minuty od půlnoci z „Y-m-d H:i:s" (UTC, jako uložené qso_at); null při chybném formátu. */
    private static function minutesFromDateTime(string $dt): ?int
    {
        if (! preg_match('/\b(\d{2}):(\d{2}):/', $dt, $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /** Atribut agregovaného řádku jako int (agregáty se vrací jako mixed). */
    private static function intAttr(Model $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Atribut agregovaného řádku jako string (prázdný při null). */
    private static function strAttr(Model $model, string $key): string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
