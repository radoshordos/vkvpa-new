<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Enums\QsoMode;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
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
 * a převzatými záznamy výsledkové listiny (`edi_entries`):
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
 * @phpstan-type StatTop array{znacka: string, kategorie: string, body: int, pocet: int, multiplier: int}
 * @phpstan-type StatOdx array{call: string, wwl: string, home: string, homeCall: string, dist: int}
 * @phpstan-type StatNazevPocet array{nazev: string, pocet: int}
 * @phpstan-type StatKat array{zkratka: string, nazev: string, pocet: int}
 * @phpstan-type StatTimeline array{labels: list<string>, counts: list<int>}
 * @phpstan-type StatMody list<array{mode: int, label: string, pocet: int}>
 * @phpstan-type StatTrend array{labels: list<string>, stanic: list<int>, qso: list<int>, body: list<int>}
 * @phpstan-type StatFakt array{key: string, params: array<string, string|int>}
 * @phpstan-type StatOdznaky array{ucast: bool, skore: bool, qso: bool, multiplier: bool}
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
     * je false, proto jen pole/skaláry). Cache se zahazuje při přepočtu
     * pořadí kola, aby veřejný detail nezůstal na starých bodech.
     *
     * @return StatPrehled
     */
    public function prehled(EdiRound $kolo): array
    {
        /** @var StatPrehled $data */
        $data = Cache::remember(
            self::cacheKey($kolo->id),
            VkvpaSettings::roundStationsCacheTtl(),
            fn (): array => $this->compute($kolo),
        );

        return $data;
    }

    public static function cacheKey(int $koloId): string
    {
        return sprintf('vkvpa:kolo-stats:v6:%d', $koloId);
    }

    public function forgetRound(int $koloId): void
    {
        Cache::forget(self::cacheKey($koloId));
    }

    /**
     * @return StatPrehled
     */
    private function compute(EdiRound $kolo): array
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
        /** @var array<int, int> $modyCount  klíč = kód módu (QsoMode->value), hodnota = počet QSO */
        $modyCount = [];
        /** @var StatOdx|null $odx */
        $odx = null;

        $rows = DB::table('edi_lines')
            ->join('edi_heads', 'edi_lines.edihead_id', '=', 'edi_heads.id')
            ->where('edi_heads.round_id', $koloId)
            ->whereTime('edi_lines.qso_at', '>=', ContestWindow::fromSqlTime())
            ->whereTime('edi_lines.qso_at', '<=', ContestWindow::toSqlTime())
            ->get([
                'edi_lines.received_wwl as wwl',
                'edi_lines.lat as lat',
                'edi_lines.lon as lon',
                'edi_lines.call_sign as call_sign',
                'edi_lines.mode_code as mode_code',
                'edi_lines.qso_at as qso_at',
                'edi_heads.p_wwlo as home_wwl',
                'edi_heads.p_call as home_call',
            ]);

        foreach ($rows as $r) {
            $wwl = strtoupper(trim(is_scalar($r->wwl) ? (string) $r->wwl : ''));
            $sq = Maidenhead::bigSquare($wwl);
            if (Maidenhead::isValidBigSquare($sq)) {
                $counts[$sq] = ($counts[$sq] ?? 0) + 1;
            }

            // Druh provozu: oficiální módy 1–6 každý zvlášť, cokoli jiného
            // (vč. rozhozeného sloupce) padá do „Ostatní" – přes QsoMode, ať je
            // to konzistentní s vizualizací deníku.
            $mode = QsoMode::fromCode(is_numeric($r->mode_code) ? (int) $r->mode_code : 0)->value;
            $modyCount[$mode] = ($modyCount[$mode] ?? 0) + 1;

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

        // Druhy provozu: jen přítomné módy, kanonické pořadí (1–6 vzestupně,
        // „Ostatní" = 0 na konec), s popiskem z QsoMode.
        uksort($modyCount, static fn (int $a, int $b): int => ($a ?: 99) <=> ($b ?: 99));
        $mody = [];
        foreach ($modyCount as $mode => $pocet) {
            $mody[] = ['mode' => $mode, 'label' => QsoMode::from($mode)->label(), 'pocet' => $pocet];
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
        $zkratky = EdiCategory::zkratkaMap();

        $entries = EdiEntry::query()
            ->where('round_id', $koloId)
            ->approved()
            ->get(['callsign', 'points', 'qso_count', 'multiplier', 'category_id']);

        $souhrn = [
            'pocetStanic' => $entries->pluck('callsign')->unique()->count(),
            'pocetZaznamu' => $entries->count(),
            'pocetQso' => (int) $entries->sum(fn (EdiEntry $e): int => $e->qso_count),
            'bodyCelkem' => (int) $entries->sum(fn (EdiEntry $e): int => $e->points),
        ];

        return [
            $souhrn,
            $this->category($koloId),
            $this->topN($entries, $zkratky, 'body'),
            $this->topN($entries, $zkratky, 'pocet'),
            $this->topN($entries, $zkratky, 'multiplier'),
        ];
    }

    /**
     * Rozdělení převzatých záznamů do kategorií (sestupně podle počtu).
     *
     * @return list<StatKat>
     */
    private function category(int $koloId): array
    {
        $out = [];
        $zkratky = EdiCategory::zkratkaMap();
        $nazvy = EdiCategory::nazevMap();
        $rows = EdiEntry::query()
            ->where('edi_entries.round_id', $koloId)
            ->where('edi_entries.approved', true)
            ->groupBy('edi_entries.category_id')
            ->selectRaw('edi_entries.category_id as category_id, COUNT(*) as pocet')
            ->get();

        foreach ($rows as $row) {
            $katId = self::intAttr($row, 'category_id');
            $out[] = [
                'zkratka' => $zkratky->get($katId) ?: '?',
                'nazev' => $nazvy->get($katId) ?: '?',
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
        $zkratky = EdiCategory::zkratkaMap();

        $out = [];
        $entries = EdiEntry::query()
            ->where('round_id', $koloId)
            ->approved()
            ->orderByDesc('points')
            ->get(['callsign', 'locator', 'points', 'rank', 'category_id']);

        foreach ($entries as $e) {
            $loc = strtoupper(trim($e->locator));
            $c = Maidenhead::toLatLon($loc) ?? Maidenhead::bigSquareCenter(Maidenhead::bigSquare($loc));
            if ($c === null) {
                continue;
            }
            $zkr = $e->category_id !== null ? $zkratky->get($e->category_id) : null;
            $out[] = [
                'lat' => $c['lat'],
                'lon' => $c['lon'],
                'call' => (string) $e->callsign,
                'loc' => $loc,
                'kat' => is_string($zkr) ? $zkr : '',
                'body' => (int) $e->points,
                'poradi' => (int) $e->rank,
            ];
        }

        return $out;
    }

    /**
     * Trend posledních $n vyhodnocených kol (včetně tohoto) – účast, QSO, body.
     *
     * @return StatTrend
     */
    private function trend(EdiRound $kolo, int $n = 12): array
    {
        $kola = EdiRound::query()
            ->whereNotNull('evaluated_at')
            ->where('starts_at', '<=', $kolo->starts_at)
            ->orderByDesc('starts_at')
            ->limit($n)
            ->get(['id', 'name', 'starts_at'])
            ->sortBy('starts_at')
            ->values();

        $agg = EdiEntry::query()
            ->where('approved', true)
            ->whereIn('round_id', $kola->pluck('id'))
            ->selectRaw('round_id, COUNT(DISTINCT callsign) as stanic, SUM(qso_count) as qso, SUM(points) as body')
            ->groupBy('round_id')
            ->get()
            ->keyBy('round_id');

        $labels = [];
        $stanic = [];
        $qso = [];
        $body = [];
        foreach ($kola as $k) {
            $a = $agg->get($k->id);
            $labels[] = (string) $k->name;
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
    private function zajimavosti(EdiRound $kolo, array $ctverce, array $topBody): array
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
    private function novacku(EdiRound $kolo): int
    {
        $earlier = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_entries.approved', true)
            ->where('edi_rounds.starts_at', '<', $kolo->starts_at)
            ->distinct()
            ->pluck('edi_entries.callsign');

        return EdiEntry::query()
            ->where('round_id', $kolo->id)
            ->approved()
            ->whereNotIn('callsign', $earlier)
            ->distinct()
            ->count('callsign');
    }

    /**
     * TOP 10 záznamů sestupně podle sloupce ($col = body|pocet|multiplier).
     *
     * @param  EloquentCollection<int, EdiEntry>  $entries
     * @param  SupportCollection<int, string>  $zkratky  id kategorie → zkratka
     * @return list<StatTop>
     */
    private function topN(EloquentCollection $entries, SupportCollection $zkratky, string $col): array
    {
        $sorted = $entries
            ->sortByDesc(fn (EdiEntry $e): int => match ($col) {
                'pocet' => $e->qso_count,
                'multiplier' => $e->multiplier,
                default => $e->points,
            })
            ->take(10);

        $out = [];
        foreach ($sorted as $e) {
            $zkr = $e->category_id !== null ? $zkratky->get($e->category_id) : null;
            $out[] = [
                'znacka' => (string) $e->callsign,
                'kategorie' => is_string($zkr) ? $zkr : '',
                'body' => (int) $e->points,
                'pocet' => (int) $e->qso_count,
                'multiplier' => (int) $e->multiplier,
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
}
