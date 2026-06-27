<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\VkvpaData;
use App\Support\VkvpaSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * All-time rekordy („síň slávy") napříč všemi vyhodnocenými koly – počítané
 * levně z výsledkové listiny (`vkvpa_data`): rekordní účast v kole, nejvyšší
 * skóre, nejvíc QSO a nejvíc násobičů jediného záznamu. ODX historie se zde
 * záměrně nepočítá (vyžadovalo by těžký sken všech `edi_lines`).
 *
 * Mění se jen při vyhodnocení nového kola, proto stačí TTL cache.
 *
 * @phpstan-type Rekord array{znacka: string, kolo: string, koloId: int, value: int}
 * @phpstan-type RekordKola array{kolo: string, koloId: int, value: int}
 * @phpstan-type RekordOdx array{dist: int, call: string, wwl: string, home: string, homeCall: string, kolo: string, koloId: int}
 * @phpstan-type Vrcholy array{ucast: RekordKola|null, skore: Rekord|null, qso: Rekord|null, nasobice: Rekord|null}
 */
final class RekordyService
{
    /** Cache all-time ODX – plní příkaz statistiky:precompute-odx, web jen čte. */
    private const ODX_CACHE = 'vkvpa:rekordy:odx:v1';

    /**
     * All-time rekordy (cachované – jen pole/skaláry).
     *
     * @return Vrcholy
     */
    public function vrcholy(): array
    {
        /** @var Vrcholy $v */
        $v = Cache::remember(
            'vkvpa:rekordy:v1',
            VkvpaSettings::roundStationsCacheTtl(),
            fn (): array => [
                'ucast' => $this->maxUcast(),
                'skore' => $this->maxZaznam('body'),
                'qso' => $this->maxZaznam('pocet'),
                'nasobice' => $this->maxZaznam('nasobice'),
            ],
        );

        return $v;
    }

    /**
     * All-time ODX (nejdelší spojení historie) – jen čtení z cache, kterou plní
     * příkaz `statistiky:precompute-odx` (výpočet je drahý sken všech deníků).
     * Null, dokud příkaz neproběhl.
     *
     * @return RekordOdx|null
     */
    public function odxAllTime(): ?array
    {
        $v = Cache::get(self::ODX_CACHE);

        /** @var RekordOdx|null $v */
        return is_array($v) ? $v : null;
    }

    /**
     * Uloží all-time ODX (volá precompute příkaz). Null vyčistí záznam.
     *
     * @param  RekordOdx|null  $odx
     */
    public function storeOdxAllTime(?array $odx): void
    {
        if ($odx === null) {
            Cache::forget(self::ODX_CACHE);

            return;
        }

        Cache::forever(self::ODX_CACHE, $odx);
    }

    /**
     * Drží dané kolo některý z all-time rekordů? (pro odznaky na detailu kola)
     *
     * @return array{ucast: bool, skore: bool, qso: bool, nasobice: bool}
     */
    public function odznakyProKolo(int $koloId): array
    {
        $v = $this->vrcholy();

        return [
            'ucast' => $v['ucast'] !== null && $v['ucast']['koloId'] === $koloId,
            'skore' => $v['skore'] !== null && $v['skore']['koloId'] === $koloId,
            'qso' => $v['qso'] !== null && $v['qso']['koloId'] === $koloId,
            'nasobice' => $v['nasobice'] !== null && $v['nasobice']['koloId'] === $koloId,
        ];
    }

    /**
     * Kolo s nejvíc unikátními značkami (rekordní účast).
     *
     * @return RekordKola|null
     */
    private function maxUcast(): ?array
    {
        $row = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->whereNotNull('vkvpa_kola.vyhodnoceno')
            ->groupBy('vkvpa_data.id_kola', 'vkvpa_kola.nazev')
            ->selectRaw('vkvpa_data.id_kola as kolo_id, vkvpa_kola.nazev as nazev, COUNT(DISTINCT vkvpa_data.znacka) as value')
            ->orderByDesc('value')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'kolo' => self::strAttr($row, 'nazev'),
            'koloId' => self::intAttr($row, 'kolo_id'),
            'value' => self::intAttr($row, 'value'),
        ];
    }

    /**
     * Záznam s nejvyšší hodnotou sloupce ($col = body|pocet|nasobice).
     *
     * @return Rekord|null
     */
    private function maxZaznam(string $col): ?array
    {
        // Literal-string select kvůli selectRaw (PHPStan L10).
        $select = match ($col) {
            'pocet' => 'vkvpa_data.znacka as znacka, vkvpa_data.id_kola as kolo_id, vkvpa_kola.nazev as nazev, vkvpa_data.pocet as value',
            'nasobice' => 'vkvpa_data.znacka as znacka, vkvpa_data.id_kola as kolo_id, vkvpa_kola.nazev as nazev, vkvpa_data.nasobice as value',
            default => 'vkvpa_data.znacka as znacka, vkvpa_data.id_kola as kolo_id, vkvpa_kola.nazev as nazev, vkvpa_data.body as value',
        };
        $orderCol = match ($col) {
            'pocet' => 'vkvpa_data.pocet',
            'nasobice' => 'vkvpa_data.nasobice',
            default => 'vkvpa_data.body',
        };

        $row = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_data.schvaleno', true)
            ->whereNotNull('vkvpa_kola.vyhodnoceno')
            ->selectRaw($select)
            ->orderByDesc($orderCol)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'znacka' => self::strAttr($row, 'znacka'),
            'kolo' => self::strAttr($row, 'nazev'),
            'koloId' => self::intAttr($row, 'kolo_id'),
            'value' => self::intAttr($row, 'value'),
        ];
    }

    /** Atribut agregovaného řádku jako int (agregáty se vrací jako mixed). */
    private static function intAttr(Model $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Atribut agregovaného řádku jako string. */
    private static function strAttr(Model $model, string $key): string
    {
        $value = $model->getAttribute($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
