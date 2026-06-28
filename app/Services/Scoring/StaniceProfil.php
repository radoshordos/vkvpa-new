<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Support\VkvpaSettings;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Veřejný profil stanice (značky) napříč všemi vyhodnocenými koly: souhrn,
 * historie účastí (kolo po kole) a trend bodů. Levné agregace z výsledkové
 * listiny (`edi_entries`); cachuje se per značka.
 *
 * @phpstan-type ProfilRadek array{koloId: int, kolo: string, datum: string, kategorie: string, pocet: int, multiplier: int, body: int, poradi: int, edihead_id: int|null}
 * @phpstan-type Profil array{
 *     znacka: string, pocetKol: int, bodyCelkem: int, qsoCelkem: int,
 *     nejlepsiPoradi: int|null, nejlepsiSkore: int,
 *     historie: list<ProfilRadek>, trend: array{labels: list<string>, body: list<int>}
 * }
 */
final class StaniceProfil
{
    /**
     * Profil značky, nebo null když nemá žádný záznam ve vyhodnoceném kole.
     *
     * @return Profil|null
     */
    public function profil(string $znacka): ?array
    {
        $znacka = strtoupper(trim($znacka));
        if ($znacka === '') {
            return null;
        }

        /** @var Profil|null $data */
        $data = Cache::remember(
            'vkvpa:stanice:v1:'.$znacka,
            VkvpaSettings::roundStationsCacheTtl(),
            fn (): ?array => $this->compute($znacka),
        );

        return $data;
    }

    /**
     * @return Profil|null
     */
    private function compute(string $znacka): ?array
    {
        /** @var SupportCollection<int, string> $zkratky */
        $zkratky = EdiCategory::zkratkaMap();

        $entries = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_entries.approved', true)
            ->whereNotNull('edi_rounds.evaluated_at')
            ->where('edi_entries.callsign', $znacka)
            ->orderBy('edi_rounds.starts_at')
            ->get([
                'edi_entries.round_id as round_id',
                'edi_rounds.name as nazev',
                'edi_rounds.starts_at as starts_at',
                'edi_entries.category_id as category_id',
                'edi_entries.qso_count',
                'edi_entries.multiplier as multiplier',
                'edi_entries.points',
                'edi_entries.rank',
                'edi_entries.edi_head_id',
            ]);

        if ($entries->isEmpty()) {
            return null;
        }

        /** @var list<ProfilRadek> $historie */
        $historie = [];
        $bodyCelkem = 0;
        $qsoCelkem = 0;
        $nejlepsiSkore = 0;
        $nejlepsiPoradi = null;
        $labels = [];
        $bodyTrend = [];

        foreach ($entries as $e) {
            $body = (int) $e->points;
            $pocet = (int) $e->qso_count;
            $poradi = (int) $e->rank;
            $zkr = $e->category_id !== null ? $zkratky->get($e->category_id) : null;

            $bodyCelkem += $body;
            $qsoCelkem += $pocet;
            $nejlepsiSkore = max($nejlepsiSkore, $body);
            if ($poradi > 0 && ($nejlepsiPoradi === null || $poradi < $nejlepsiPoradi)) {
                $nejlepsiPoradi = $poradi;
            }

            $nazev = self::strAttr($e, 'nazev');
            $historie[] = [
                'koloId' => self::intAttr($e, 'round_id'),
                'kolo' => $nazev,
                'datum' => substr(self::strAttr($e, 'starts_at'), 0, 10),
                'kategorie' => is_string($zkr) ? $zkr : '',
                'pocet' => $pocet,
                'multiplier' => (int) $e->multiplier,
                'body' => $body,
                'poradi' => $poradi,
                'edihead_id' => $e->edi_head_id,
            ];
            $labels[] = $nazev;
            $bodyTrend[] = $body;
        }

        return [
            'znacka' => $znacka,
            'pocetKol' => count($historie),
            'bodyCelkem' => $bodyCelkem,
            'qsoCelkem' => $qsoCelkem,
            'nejlepsiPoradi' => $nejlepsiPoradi,
            'nejlepsiSkore' => $nejlepsiSkore,
            'historie' => $historie,
            'trend' => ['labels' => $labels, 'body' => $bodyTrend],
        ];
    }

    /** Atribut řádku jako int (aliasované sloupce se vrací jako mixed). */
    private static function intAttr(EdiEntry $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Atribut řádku jako string. */
    private static function strAttr(EdiEntry $model, string $key): string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
