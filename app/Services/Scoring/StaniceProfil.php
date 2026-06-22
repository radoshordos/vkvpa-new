<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Support\VkvpaSettings;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

/**
 * Veřejný profil stanice (značky) napříč všemi vyhodnocenými koly: souhrn,
 * historie účastí (kolo po kole) a trend bodů. Levné agregace z výsledkové
 * listiny (`vkvpa_data`); cachuje se per značka.
 *
 * @phpstan-type ProfilRadek array{koloId: int, kolo: string, datum: string, kategorie: string, pocet: int, nasobice: int, body: int, poradi: int, edihead_id: int|null}
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
        $zkratky = VkvpaKategorie::query()->pluck('zkratka', 'id');

        $entries = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->whereNotNull('vkvpa_kola.vyhodnoceno')
            ->where('vkvpa_data.znacka', $znacka)
            ->orderBy('vkvpa_kola.datum_konani')
            ->get([
                'vkvpa_data.id_kola as kolo_id',
                'vkvpa_kola.nazev as nazev',
                'vkvpa_kola.datum_konani as datum_konani',
                'vkvpa_data.id_kategorie as id_kategorie',
                'vkvpa_data.pocet as pocet',
                'vkvpa_data.nasobice as nasobice',
                'vkvpa_data.body as body',
                'vkvpa_data.poradi as poradi',
                'vkvpa_data.edihead_id as edihead_id',
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
            $body = (int) $e->body;
            $pocet = (int) $e->pocet;
            $poradi = (int) $e->poradi;
            $zkr = $e->id_kategorie !== null ? $zkratky->get($e->id_kategorie) : null;

            $bodyCelkem += $body;
            $qsoCelkem += $pocet;
            $nejlepsiSkore = max($nejlepsiSkore, $body);
            if ($poradi > 0 && ($nejlepsiPoradi === null || $poradi < $nejlepsiPoradi)) {
                $nejlepsiPoradi = $poradi;
            }

            $nazev = self::strAttr($e, 'nazev');
            $historie[] = [
                'koloId' => self::intAttr($e, 'kolo_id'),
                'kolo' => $nazev,
                'datum' => substr(self::strAttr($e, 'datum_konani'), 0, 10),
                'kategorie' => is_string($zkr) ? $zkr : '',
                'pocet' => $pocet,
                'nasobice' => (int) $e->nasobice,
                'body' => $body,
                'poradi' => $poradi,
                'edihead_id' => $e->edihead_id,
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
    private static function intAttr(VkvpaData $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Atribut řádku jako string. */
    private static function strAttr(VkvpaData $model, string $key): string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
