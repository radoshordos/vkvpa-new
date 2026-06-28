<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Http\Controllers\EdiStatInkubatorController;
use App\Models\Edihead;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;

/**
 * INKUBÁTOR – prototypy nových statistik pro stránku „Statistiky-inkubátor"
 * ({@see EdiStatInkubatorController}). Slouží jako
 * hřiště, ze kterého se vybrané funkce po odladění přesunou do
 * {@see DenikStatistiky}. Nic z toho zatím není napojené na produkční
 * vizualizaci.
 *
 * Funkce závislé na cizích denících (porovnání s polem kategorie, promarněné
 * příležitosti) ctí stejné férovostní pravidlo jako zbytek aplikace: pole se
 * sestaví jen z deníků, které {@see PorovnaniRivals} vydá až po uzávěrce kola.
 */
final class StatistikyInkubator
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly DenikStatistiky $statistiky,
        private readonly PorovnaniRivals $rivals,
    ) {}

    /**
     * Heatmapa směr × čas: pro každý 15minutový interval závodního okna a každý
     * z 16 azimutových sektorů počet QSO (a součet kilometrů). Ukáže, kdy se kam
     * pásmo otevřelo (shluk dalekých QSO v jednom směru v jeden čas = tropo
     * otevření). Potřebuje jen vlastní deník, takže funguje i během příjmu.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{casy: list<string>, smery: list<string>, pocet: list<list<int>>, km: list<list<int>>, maxPocet: int}
     */
    public function heatmapaSmerCas(Collection $lines, int $fromMin, int $toMin): array
    {
        $casy = $this->statistiky->timelineLabels($fromMin, $toMin);
        $cols = count($casy);
        $smery = ['S', 'SSV', 'SV', 'VSV', 'V', 'VJV', 'JV', 'JJV', 'J', 'JJZ', 'JZ', 'ZJZ', 'Z', 'ZSZ', 'SZ', 'SSZ'];

        $pocet = array_fill(0, 16, array_fill(0, $cols, 0));
        $km = array_fill(0, 16, array_fill(0, $cols, 0));
        $max = 0;

        foreach ($lines as $l) {
            if ($l->azimut === null) {
                continue;
            }

            $sektor = (int) (($l->azimut + 11.25) / 22.5) % 16;
            $col = $l->timeMinutes === $toMin ? $cols - 1 : intdiv($l->timeMinutes - $fromMin, 15);
            if ($col < 0 || $col >= $cols) {
                continue;
            }

            $pocet[$sektor][$col]++;
            $km[$sektor][$col] += $l->dist ?? 0;
            $max = max($max, $pocet[$sektor][$col]);
        }

        return [
            'casy' => $casy,
            'smery' => $smery,
            // Po inkrementacích PHPStan ztrácí jistotu, že jde o list – obnovíme ji.
            'pocet' => $this->naListy($pocet),
            'km' => $this->naListy($km),
            'maxPocet' => $max,
        ];
    }

    /**
     * Souhrnná analýza vůči poli kategorie (já + všichni soupeři z téhož kola
     * a kategorie). Vrací podklady pro:
     *  – graf „vs. pole" (moje průběžné skóre vs. medián a kvartilové pásmo),
     *  – „závod" TOP soupeřů (kumulativní křivky pro animaci),
     *  – rate sheet (moje QSO/15 min vs. medián pole),
     *  – promarněné příležitosti (čtverce a stanice, které pracovala většina
     *    pole, ale já ne).
     *
     * Null, když porovnání není dostupné (bez kola, před uzávěrkou, bez soupeřů).
     *
     * @param  array{lat: float, lon: float}|null  $home  souřadnice domácího QTH
     * @return array{
     *     stanic: int,
     *     ticks: list<string>,
     *     mojeBody: list<int>,
     *     median: list<int>,
     *     p25: list<int>,
     *     p75: list<int>,
     *     raceMine: list<array{t: int, body: int}>,
     *     race: list<array{call: string, body: list<array{t: int, body: int}>}>,
     *     rateLabels: list<string>,
     *     rateMoje: list<int>,
     *     rateMedian: list<int>,
     *     missedSquares: list<array{square: string, kolik: int, body: int}>,
     *     missedStations: list<array{call: string, wwl: string, dist: int|null, kolik: int}>
     * }|null
     */
    public function analyzaPole(Edihead $head, ?array $home, string $homeSq, int $fromMin, int $toMin): ?array
    {
        $rivals = $this->rivals->rivals($head);
        if ($rivals->isEmpty()) {
            return null;
        }

        $ticks = $this->ticks($fromMin, $toMin);

        // ── Moje strana ────────────────────────────────────────────────────
        $mojeQso = $this->geometry->enrichedQsos($head, $home, 'qso_at');
        $mojeCum = $this->geometry->prubehSkore($mojeQso, $homeSq);
        $mojeBody = $this->sample($mojeCum, $ticks);
        $rateMoje = $this->statistiky->timelineCounts($mojeQso, $fromMin, $toMin);
        $mojeCtverce = $this->distinctSquares($mojeQso);
        $mojeStanice = $this->distinctCalls($mojeQso);

        // ── Pole (medián/kvartily) + sběr popularit pro promarněné ─────────
        $poleBody = [$mojeBody];      // sloupce pro medián: já + soupeři
        $poleRate = [$rateMoje];
        // Pro „závod" si vedle vzorkovaných bodů držím i surovou kumulativní
        // řadu (bod v čase každého QSO) – animace pak roste minutu po minutě.
        /** @var list<array{call: string, final: int, raw: list<array{t: int, body: int}>}> $souperi */
        $souperi = [];
        /** @var array<string, int> $ctverecPop  kolik soupeřů čtverec pracovalo */
        $ctverecPop = [];
        /** @var array<string, int> $stanicePop */
        $stanicePop = [];
        /** @var array<string, array{wwl: string, lat: float, lon: float}> $staniceInfo */
        $staniceInfo = [];

        foreach ($rivals as $rival) {
            $rivalHome = Maidenhead::toLatLon((string) $rival->p_wwlo);
            $rivalSq = Maidenhead::bigSquare((string) $rival->p_wwlo);
            $rivalQso = $this->geometry->enrichedQsos($rival, $rivalHome, 'qso_at');

            $cum = $this->geometry->prubehSkore($rivalQso, $rivalSq);
            $body = $this->sample($cum, $ticks);
            $poleBody[] = $body;
            $poleRate[] = $this->statistiky->timelineCounts($rivalQso, $fromMin, $toMin);
            $souperi[] = [
                'call' => (string) $rival->p_call,
                'final' => $body === [] ? 0 : $body[count($body) - 1],
                'raw' => $this->rawBody($cum),
            ];

            foreach ($this->distinctSquares($rivalQso) as $sq) {
                $ctverecPop[$sq] = ($ctverecPop[$sq] ?? 0) + 1;
            }

            foreach ($rivalQso as $q) {
                $call = strtoupper(trim($q->call));
                if ($call === '') {
                    continue;
                }
                $staniceInfo[$call] ??= ['wwl' => $q->wwl, 'lat' => $q->lat, 'lon' => $q->lon];
            }
            foreach ($this->distinctCalls($rivalQso) as $call) {
                $stanicePop[$call] = ($stanicePop[$call] ?? 0) + 1;
            }
        }

        $stanic = count($rivals) + 1;
        $prah = max(2, (int) ceil(count($rivals) * 0.25));

        return [
            'stanic' => $stanic,
            'ticks' => $ticks['labels'],
            'mojeBody' => $mojeBody,
            'median' => $this->percentilSloupce($poleBody, 0.5),
            'p25' => $this->percentilSloupce($poleBody, 0.25),
            'p75' => $this->percentilSloupce($poleBody, 0.75),
            'raceMine' => $this->rawBody($mojeCum),
            'race' => $this->topZavod($souperi, 5),
            'rateLabels' => $this->statistiky->timelineLabels($fromMin, $toMin),
            'rateMoje' => $rateMoje,
            'rateMedian' => $this->percentilSloupce($poleRate, 0.5),
            'missedSquares' => $this->promarneneCtverce($ctverecPop, $mojeCtverce, $homeSq, $prah),
            'missedStations' => $this->promarneneStanice($stanicePop, $staniceInfo, $mojeStanice, $home, $prah),
        ];
    }

    /**
     * Velké čtverce → list<list<int>> (obnova typu list po inkrementacích).
     *
     * @param  array<int, array<int, int>>  $m
     * @return list<list<int>>
     */
    private function naListy(array $m): array
    {
        return array_values(array_map(static fn (array $r) => array_values($r), $m));
    }

    /**
     * Promarněné čtverce: velké čtverce, které pracovalo aspoň $prah soupeřů,
     * ale já ne. `body` = bodová hodnota jednoho QSO do toho čtverce z domácího
     * QTH (zároveň by šlo o nový násobič). Seřazeno podle popularity.
     *
     * @param  array<array-key, int>  $pop
     * @param  array<string, true>  $moje
     * @return list<array{square: string, kolik: int, body: int}>
     */
    private function promarneneCtverce(array $pop, array $moje, string $homeSq, int $prah): array
    {
        $out = [];

        foreach ($pop as $sq => $kolik) {
            $sq = (string) $sq;
            if ($kolik < $prah || isset($moje[$sq])) {
                continue;
            }
            $out[] = ['square' => $sq, 'kolik' => $kolik, 'body' => Maidenhead::qsoPoints($homeSq, $sq)];
        }

        usort($out, fn (array $a, array $b): int => $b['kolik'] <=> $a['kolik'] ?: strcmp($a['square'], $b['square']));

        return array_slice($out, 0, 30);
    }

    /**
     * Promarněné stanice: protistanice, které pracovalo aspoň $prah soupeřů,
     * ale já ne. Vzdálenost se počítá od mého QTH. Seřazeno podle popularity,
     * při shodě podle vzdálenosti.
     *
     * @param  array<array-key, int>  $pop
     * @param  array<string, array{wwl: string, lat: float, lon: float}>  $info
     * @param  array<string, true>  $moje
     * @param  array{lat: float, lon: float}|null  $home
     * @return list<array{call: string, wwl: string, dist: int|null, kolik: int}>
     */
    private function promarneneStanice(array $pop, array $info, array $moje, ?array $home, int $prah): array
    {
        $out = [];

        foreach ($pop as $call => $kolik) {
            $call = (string) $call;
            if ($kolik < $prah || isset($moje[$call]) || ! isset($info[$call])) {
                continue;
            }

            $dist = $home === null
                ? null
                : (int) round(Maidenhead::distanceKm($home['lat'], $home['lon'], $info[$call]['lat'], $info[$call]['lon']));

            $out[] = ['call' => $call, 'wwl' => $info[$call]['wwl'], 'dist' => $dist, 'kolik' => $kolik];
        }

        usort($out, fn (array $a, array $b): int => $b['kolik'] <=> $a['kolik'] ?: ($b['dist'] ?? 0) <=> ($a['dist'] ?? 0));

        return array_slice($out, 0, 30);
    }

    /**
     * TOP soupeři podle výsledných bodů pro „závod"; vrací jejich surovou
     * kumulativní řadu (bod v čase každého QSO) pro animaci po minutách.
     *
     * @param  list<array{call: string, final: int, raw: list<array{t: int, body: int}>}>  $souperi
     * @return list<array{call: string, body: list<array{t: int, body: int}>}>
     */
    private function topZavod(array $souperi, int $limit): array
    {
        usort($souperi, fn (array $a, array $b): int => $b['final'] <=> $a['final']);

        return array_map(
            static fn (array $s): array => ['call' => $s['call'], 'body' => $s['raw']],
            array_slice($souperi, 0, $limit),
        );
    }

    /**
     * Surová kumulativní řada skóre: pro každé QSO jeho čas a průběžné body
     * (podklad pro animovaný „závod" na lineární časové ose).
     *
     * @param  list<array{t: int, cas: string, call: string, points: int, multiplier: int, body: int}>  $cum
     * @return list<array{t: int, body: int}>
     */
    private function rawBody(array $cum): array
    {
        return array_map(static fn (array $c): array => ['t' => $c['t'], 'body' => $c['body']], $cum);
    }

    /**
     * Vzorkování kumulativní (neklesající) řady skóre v časových ticích:
     * pro každý tik poslední `body`, jehož čas QSO je ≤ tik.
     *
     * @param  list<array{t: int, cas: string, call: string, points: int, multiplier: int, body: int}>  $cum
     * @param  array{minutes: list<int>, labels: list<string>}  $ticks
     * @return list<int>
     */
    private function sample(array $cum, array $ticks): array
    {
        $out = [];
        $j = 0;
        $last = 0;
        $n = count($cum);

        foreach ($ticks['minutes'] as $tk) {
            while ($j < $n && $cum[$j]['t'] <= $tk) {
                $last = $cum[$j]['body'];
                $j++;
            }
            $out[] = $last;
        }

        return $out;
    }

    /**
     * Medián / kvartil v každém sloupci (čase) napříč stanicemi pole.
     *
     * @param  list<list<int>>  $sloupce  jedna řada hodnot na stanici
     * @return list<int>
     */
    private function percentilSloupce(array $sloupce, float $p): array
    {
        $delka = $sloupce === [] ? 0 : count($sloupce[0]);
        $out = [];

        for ($i = 0; $i < $delka; $i++) {
            $col = array_map(static fn (array $s): int => $s[$i] ?? 0, $sloupce);
            sort($col);
            // $sloupce je v této větvi neprázdné (jinak $delka = 0), $col tedy taky.
            $out[] = $col[(int) floor($p * (count($col) - 1))];
        }

        return $out;
    }

    /**
     * Časové tiky závodního okna po 15 minutách (včetně koncového), s popisky.
     *
     * @return array{minutes: list<int>, labels: list<string>}
     */
    private function ticks(int $fromMin, int $toMin): array
    {
        $minutes = [];
        $labels = [];

        for ($m = $fromMin; $m <= $toMin; $m += 15) {
            $minutes[] = $m;
            $labels[] = DenikStatistiky::hhmm($m);
        }

        return ['minutes' => $minutes, 'labels' => $labels];
    }

    /**
     * Množina platných velkých čtverců protistanic deníku (klíč = čtverec).
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array<string, true>
     */
    private function distinctSquares(Collection $lines): array
    {
        $out = [];

        foreach ($lines as $l) {
            $sq = Maidenhead::bigSquare($l->wwl);
            if (Maidenhead::isValidBigSquare($sq)) {
                $out[$sq] = true;
            }
        }

        return $out;
    }

    /**
     * Množina normalizovaných značek protistanic deníku (klíč = značka).
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array<string, true>
     */
    private function distinctCalls(Collection $lines): array
    {
        $out = [];

        foreach ($lines as $l) {
            $call = strtoupper(trim($l->call));
            if ($call !== '') {
                $out[$call] = true;
            }
        }

        return $out;
    }
}
