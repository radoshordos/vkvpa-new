<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;

/**
 * Statistiky a agregace deníku pro stránku Vizualizace: nové násobiče,
 * timeline, vážená azimutová růžice, body podle čtverců, TOP ODX, souhrn
 * po druzích provozu, tempo závodu, nezapočítaná QSO a celoroční trend
 * stanice. Geometrii spojení dodává {@see QsoGeometry}.
 */
class DenikStatistiky
{
    /**
     * Nové násobiče v chronologickém pořadí: které QSO přineslo dosud
     * nepracovaný velký čtverec. Vlastní čtverec se jako násobič počítá
     * automaticky od začátku, proto v seznamu „nových" nefiguruje.
     * `idx` je pozice QSO v $lines – mapa podle ní zvýrazňuje špendlíky.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return list<array{square: string, call: string, cas: string, t: int, poradi: int, idx: int}>
     */
    public function noveNasobice(Collection $lines, string $homeSq): array
    {
        /** @var array<string, true> $seen */
        $seen = [];
        $poradi = 0;
        if (Maidenhead::isValidBigSquare($homeSq)) {
            $seen[$homeSq] = true;
            $poradi = 1;
        }

        $out = [];

        foreach ($lines as $idx => $l) {
            $sq = Maidenhead::bigSquare($l->wwl);
            if (! Maidenhead::isValidBigSquare($sq) || isset($seen[$sq])) {
                continue;
            }

            $seen[$sq] = true;
            $poradi++;
            $out[] = [
                'square' => $sq,
                'call' => $l->call,
                'cas' => self::hhmm($l->timeMinutes),
                't' => $l->timeMinutes,
                'poradi' => $poradi,
                'idx' => $idx,
            ];
        }

        return $out;
    }

    /**
     * 15minutové intervaly závodního okna: celkový počet QSO a z toho QSO,
     * která přinesla nový násobič (pro skládaný sloupcový graf).
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @param  list<array{square: string, call: string, cas: string, t: int, poradi: int, idx: int}>  $nasobice
     * @return array{labels: list<string>, celkem: list<int>, nove: list<int>}
     */
    public function timeline(Collection $lines, array $nasobice, int $fromMin, int $toMin): array
    {
        $count = max(1, (int) ceil(($toMin - $fromMin) / 15));

        $labels = [];
        $celkem = array_fill(0, $count, 0);
        $nove = array_fill(0, $count, 0);

        for ($i = 0; $i < $count; $i++) {
            $labels[] = self::hhmm($fromMin + $i * 15);
        }

        $bucket = function (int $t) use ($fromMin, $toMin, $count): ?int {
            // QSO přesně na konci okna patří do posledního intervalu.
            $i = $t === $toMin ? $count - 1 : intdiv($t - $fromMin, 15);

            return ($i >= 0 && $i < $count) ? $i : null;
        };

        foreach ($lines as $l) {
            $i = $bucket($l->timeMinutes);
            if ($i !== null) {
                $celkem[$i]++;
            }
        }

        foreach ($nasobice as $n) {
            $i = $bucket($n['t']);
            if ($i !== null) {
                $nove[$i]++;
            }
        }

        return ['labels' => $labels, 'celkem' => array_values($celkem), 'nove' => array_values($nove)];
    }

    /**
     * Azimutová růžice s trojím vážením: počet QSO, součet kilometrů a součet
     * bodů v každém ze 16 sektorů (22,5°) po směru hodinových ručiček od severu.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{labels: list<string>, pocet: list<int>, km: list<int>, body: list<int>}
     */
    public function azimuthRose(Collection $lines): array
    {
        $pocet = array_fill(0, 16, 0);
        $km = array_fill(0, 16, 0);
        $body = array_fill(0, 16, 0);

        foreach ($lines as $l) {
            if ($l->azimut === null) {
                continue;
            }
            $s = (int) (($l->azimut + 11.25) / 22.5) % 16;
            $pocet[$s]++;
            $km[$s] += $l->dist ?? 0;
            $body[$s] += $l->points;
        }

        return [
            'labels' => ['S', 'SSV', 'SV', 'VSV', 'V', 'VJV', 'JV', 'JJV', 'J', 'JJZ', 'JZ', 'ZJZ', 'Z', 'ZSZ', 'SZ', 'SSZ'],
            'pocet' => array_values($pocet),
            'km' => array_values($km),
            'body' => array_values($body),
        ];
    }

    /**
     * Kolik bodů přinesl který velký čtverec (počet QSO × bodová hodnota
     * vzdálenostního pásu), seřazeno od nejvýdělečnějšího.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return list<array{square: string, pocet: int, body: int}>
     */
    public function bodyPodleCtvercu(Collection $lines): array
    {
        /** @var array<string, array{square: string, pocet: int, body: int}> $by */
        $by = [];

        foreach ($lines as $l) {
            $sq = Maidenhead::bigSquare($l->wwl);
            if (! Maidenhead::isValidBigSquare($sq)) {
                continue;
            }
            $by[$sq] ??= ['square' => $sq, 'pocet' => 0, 'body' => 0];
            $by[$sq]['pocet']++;
            $by[$sq]['body'] += $l->points;
        }

        usort($by, fn (array $a, array $b): int => $b['body'] <=> $a['body']);

        return $by;
    }

    /**
     * TOP nejvzdálenější spojení (ODX). `idx` je pozice QSO v $lines –
     * klik na řádek tabulky podle ní najde špendlík na mapě.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return list<array{call: string, wwl: string, dist: int, azimut: int|null, cas: string, mode: string, points: int, idx: int}>
     */
    public function topOdx(Collection $lines, int $limit = 10): array
    {
        return array_values($lines
            ->filter(fn (EnrichedQso $l): bool => $l->dist !== null)
            ->sortByDesc(fn (EnrichedQso $l): int => (int) $l->dist)
            ->take($limit)
            ->map(fn (EnrichedQso $l, int $idx): array => [
                'call' => $l->call,
                'wwl' => $l->wwl,
                'dist' => (int) $l->dist,
                'azimut' => $l->azimut,
                'cas' => self::hhmm($l->timeMinutes),
                'mode' => $l->mode->label(),
                'points' => $l->points,
                'idx' => $idx,
            ])
            ->all());
    }

    /**
     * Souhrn po druzích provozu (SSB/CW/?): počet QSO, body, průměrná
     * a maximální vzdálenost.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return list<array{label: string, pocet: int, body: int, avgDist: int, maxDist: int}>
     */
    public function modeStats(Collection $lines): array
    {
        /** @var array<string, array{label: string, pocet: int, body: int, dists: list<int>}> $by */
        $by = [];

        foreach ($lines as $l) {
            $key = $l->mode->label();
            $by[$key] ??= ['label' => $key, 'pocet' => 0, 'body' => 0, 'dists' => []];
            $by[$key]['pocet']++;
            $by[$key]['body'] += $l->points;
            if ($l->dist !== null) {
                $by[$key]['dists'][] = $l->dist;
            }
        }

        $out = [];

        foreach ($by as $m) {
            $out[] = [
                'label' => $m['label'],
                'pocet' => $m['pocet'],
                'body' => $m['body'],
                'avgDist' => $m['dists'] === [] ? 0 : (int) round(array_sum($m['dists']) / count($m['dists'])),
                'maxDist' => $m['dists'] === [] ? 0 : max($m['dists']),
            ];
        }

        return $out;
    }

    /**
     * Tempo závodu: nejlepší klouzavá hodina, nejdelší pauza mezi QSO
     * a průměrný počet QSO za hodinu okna.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{spicka: string|null, spickaQso: int, pauza: int|null, pauzaKdy: string|null, prumer: float}
     */
    public function tempo(Collection $lines, int $fromMin, int $toMin): array
    {
        /** @var list<int> $times */
        $times = $lines->map(fn (EnrichedQso $l): int => $l->timeMinutes)->sort()->values()->all();
        $n = count($times);

        $spicka = null;
        $spickaQso = 0;
        for ($i = 0, $j = 0; $i < $n; $i++) {
            while ($times[$i] - $times[$j] >= 60) {
                $j++;
            }
            $cnt = $i - $j + 1;
            if ($cnt > $spickaQso) {
                $spickaQso = $cnt;
                $spicka = self::hhmm($times[$j]).'–'.self::hhmm(min($times[$j] + 60, $toMin));
            }
        }

        $pauza = null;
        $pauzaKdy = null;
        for ($i = 1; $i < $n; $i++) {
            $gap = $times[$i] - $times[$i - 1];
            if ($pauza === null || $gap > $pauza) {
                $pauza = $gap;
                $pauzaKdy = self::hhmm($times[$i - 1]).'–'.self::hhmm($times[$i]);
            }
        }

        $hours = max(1, $toMin - $fromMin) / 60;

        return [
            'spicka' => $spicka,
            'spickaQso' => $spickaQso,
            'pauza' => $pauza,
            'pauzaKdy' => $pauzaKdy,
            'prumer' => round($n / $hours, 1),
        ];
    }

    /**
     * QSO, která se do skóre nepočítají (mimo závodní okno, jiný den než
     * závod), plus QSO v deníku označená jako duplicitní (ta se ve skóre
     * počítají, ale závodníka zajímají). Výpis je oříznut na 50 řádků.
     *
     * @return array{celkem: int, radky: list<array{call: string, cas: string, duvod: string}>}
     */
    public function nezapocitana(Edihead $head): array
    {
        $den = ContestWindow::dayFromTDate((string) $head->t_date);
        $from = ContestWindow::from();
        $to = ContestWindow::to();

        $radky = [];

        foreach ($head->lines()->orderBy('time')->get(['call_sign', 'time', 'date', 'duplicate_qso_d']) as $l) {
            $time = trim((string) $l->time);
            $duvod = null;

            if ($time < $from || $time > $to) {
                $duvod = 'mimo závodní okno';
            } elseif ($den !== '' && trim((string) $l->date) !== $den) {
                $duvod = 'jiný den než závod';
            } elseif (trim((string) $l->duplicate_qso_d) !== '') {
                $duvod = 'v deníku označeno jako duplicita (D)';
            }

            if ($duvod !== null) {
                $radky[] = [
                    'call' => (string) $l->call_sign,
                    'cas' => strlen($time) === 4 ? substr($time, 0, 2).':'.substr($time, 2, 2) : $time,
                    'duvod' => $duvod,
                ];
            }
        }

        return ['celkem' => count($radky), 'radky' => array_slice($radky, 0, 50)];
    }

    /**
     * Celoroční trend stanice: body a pořadí ve všech kolech roku, do kterého
     * patří kolo tohoto deníku. Rok se bere z konce názvu kola (stejná
     * konvence jako roční výsledky); záznamy z veřejné výsledkové listiny
     * (jen schválené). Null, když deník nemá kolo nebo stanice nemá záznamy.
     *
     * @return array{labels: list<string>, body: list<int|null>, poradi: list<int|null>}|null
     */
    public function sezona(Edihead $head): ?array
    {
        if ($head->id_kola === null) {
            return null;
        }

        $kolo = VkvpaKola::query()->find($head->id_kola);
        if ($kolo === null || preg_match('/(\d{4})\s*$/', $kolo->nazev, $m) !== 1) {
            return null;
        }

        $kola = VkvpaKola::query()
            ->where('nazev', 'like', '%'.$m[1])
            ->orderBy('datum_konani')
            ->get(['id', 'nazev']);

        $entries = VkvpaData::query()
            ->approved()
            ->where('znacka', (string) $head->p_call)
            ->whereIn('id_kola', $kola->pluck('id'))
            ->orderBy('id')
            ->get(['id_kola', 'body', 'poradi'])
            ->keyBy('id_kola');

        if ($entries->isEmpty()) {
            return null;
        }

        $labels = [];
        $body = [];
        $poradi = [];

        foreach ($kola as $k) {
            $e = $entries->get($k->id);
            $labels[] = $k->nazev;
            $body[] = $e?->body;
            $poradi[] = ($e !== null && $e->poradi > 0) ? $e->poradi : null;
        }

        return ['labels' => $labels, 'body' => $body, 'poradi' => $poradi];
    }

    /** Čas „HHMM" → minuty od půlnoci. */
    public static function minutes(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 2, 2);
    }

    /** Minuty od půlnoci → „HH:MM". */
    public static function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
