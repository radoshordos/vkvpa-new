<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\EdiEntry;
use App\Support\VkvpaSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * All-time rekordy („síň slávy") napříč všemi vyhodnocenými koly – počítané
 * levně z výsledkové listiny (`edi_entries`): rekordní účast v kole, nejvyšší
 * skóre, nejvíc QSO a nejvíc násobičů jediného záznamu. ODX historie se zde
 * záměrně nepočítá (vyžadovalo by těžký sken všech `edilines`).
 *
 * Mění se jen při vyhodnocení nového kola, proto stačí TTL cache.
 *
 * @phpstan-type Rekord array{znacka: string, kolo: string, koloId: int, value: int}
 * @phpstan-type RekordKola array{kolo: string, koloId: int, value: int}
 * @phpstan-type RekordOdx array{dist: int, call: string, wwl: string, home: string, homeCall: string, kolo: string, koloId: int}
 * @phpstan-type Vrcholy array{ucast: RekordKola|null, skore: Rekord|null, qso: Rekord|null, multiplier: Rekord|null}
 */
final class RekordyService
{
    /** Cache all-time ODX – plní příkaz statistiky:precompute-odx, web jen čte. */
    private const VRCHOLY_CACHE = 'vkvpa:rekordy:v2';

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
            self::VRCHOLY_CACHE,
            VkvpaSettings::roundStationsCacheTtl(),
            fn (): array => [
                'ucast' => $this->maxUcast(),
                'skore' => $this->maxZaznam('points'),
                'qso' => $this->maxZaznam('qso_count'),
                'multiplier' => $this->maxZaznam('multiplier'),
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
     * @return array{ucast: bool, skore: bool, qso: bool, multiplier: bool}
     */
    public function odznakyProKolo(int $koloId): array
    {
        $v = $this->vrcholy();

        return [
            'ucast' => $v['ucast'] !== null && $v['ucast']['koloId'] === $koloId,
            'skore' => $v['skore'] !== null && $v['skore']['koloId'] === $koloId,
            'qso' => $v['qso'] !== null && $v['qso']['koloId'] === $koloId,
            'multiplier' => $v['multiplier'] !== null && $v['multiplier']['koloId'] === $koloId,
        ];
    }

    /**
     * Kolo s nejvíc unikátními značkami (rekordní účast).
     *
     * @return RekordKola|null
     */
    private function maxUcast(): ?array
    {
        $row = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_entries.approved', true)
            ->whereNotNull('edi_rounds.evaluated_at')
            ->groupBy('edi_entries.round_id', 'edi_rounds.name')
            ->selectRaw('edi_entries.round_id as round_id, edi_rounds.name as nazev, COUNT(DISTINCT edi_entries.callsign) as value')
            ->orderByDesc('value')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'kolo' => self::strAttr($row, 'nazev'),
            'koloId' => self::intAttr($row, 'round_id'),
            'value' => self::intAttr($row, 'value'),
        ];
    }

    /**
     * Záznam s nejvyšší hodnotou sloupce ($col = body|pocet|multiplier).
     *
     * @return Rekord|null
     */
    private function maxZaznam(string $col): ?array
    {
        // Literal-string select kvůli selectRaw (PHPStan L10).
        $select = match ($col) {
            'qso_count' => 'edi_entries.callsign as znacka, edi_entries.round_id as round_id, edi_rounds.name as nazev, edi_entries.qso_count as value',
            'multiplier' => 'edi_entries.callsign as znacka, edi_entries.round_id as round_id, edi_rounds.name as nazev, edi_entries.multiplier as value',
            default => 'edi_entries.callsign as znacka, edi_entries.round_id as round_id, edi_rounds.name as nazev, edi_entries.points as value',
        };
        $orderCol = match ($col) {
            'qso_count' => 'edi_entries.qso_count',
            'multiplier' => 'edi_entries.multiplier',
            default => 'edi_entries.points',
        };

        $row = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_entries.approved', true)
            ->whereNotNull('edi_rounds.evaluated_at')
            ->selectRaw($select)
            ->orderByDesc($orderCol)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'znacka' => self::strAttr($row, 'znacka'),
            'kolo' => self::strAttr($row, 'nazev'),
            'koloId' => self::intAttr($row, 'round_id'),
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
