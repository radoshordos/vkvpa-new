<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\EdiBand;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use Illuminate\Database\Eloquent\Model;

/**
 * Dlouhodobý trend podílu pásem napříč všemi vyhodnocenými koly pro
 * samostatnou stránku „Dlouhodobé trendy" (StatistikyController::trendy()).
 *
 * Pro každé vyhodnocené kolo počítá počet různých značek na pásmu
 * (`COUNT(DISTINCT callsign)`) z převzatých záznamů listiny; pásmo se bere
 * z normalizovaného číselníku přes `edi_categories.band_id` (ne z nespolehlivé
 * hlavičky deníku), záznamy s neznámým pásmem (`band_id` NULL) se vynechají.
 * Jedna stanice může jet víc pásem, takže součet napříč pásmy bývá > počet
 * unikátních značek kola – základ pro 100% podíl je právě tento součet.
 *
 * Vrací absolutní počty stanic + rok u každého kola; procenta, 100% skládání
 * i filtr osy X podle rozsahu let dopočítá front-end, proto se posílají data za
 * celou historii najednou.
 *
 * @phpstan-type PasmaTrendData array{rounds: list<array{name: string, year: int}>, bands: list<array{token: string, name: string}>, stanice: list<list<int>>}
 */
final class PasmaTrend
{
    /**
     * Trend přes všechna vyhodnocená kola (chronologicky).
     *
     * @return PasmaTrendData
     */
    public function vsechna(): array
    {
        $kola = EdiRound::query()
            ->whereNotNull('evaluated_at')
            ->orderBy('starts_at')
            ->get(['id', 'name', 'starts_at']);

        if ($kola->isEmpty()) {
            return ['rounds' => [], 'bands' => [], 'stanice' => []];
        }

        $rows = EdiEntry::query()
            ->join('edi_categories', 'edi_entries.category_id', '=', 'edi_categories.id')
            ->where('edi_entries.approved', true)
            ->whereIn('edi_entries.round_id', $kola->pluck('id'))
            ->whereNotNull('edi_categories.band_id')
            ->groupBy('edi_entries.round_id', 'edi_categories.band_id')
            ->selectRaw('edi_entries.round_id as round_id, edi_categories.band_id as band_id, COUNT(DISTINCT edi_entries.callsign) as stanic')
            ->get();

        if ($rows->isEmpty()) {
            return ['rounds' => [], 'bands' => [], 'stanice' => []];
        }

        // counts[round_id][band_id] = počet různých značek; bandSet = přítomná pásma.
        /** @var array<int, array<int, int>> $counts */
        $counts = [];
        /** @var array<int, true> $bandSet */
        $bandSet = [];
        foreach ($rows as $r) {
            $rid = self::intAttr($r, 'round_id');
            $bid = self::intAttr($r, 'band_id');
            $counts[$rid][$bid] = self::intAttr($r, 'stanic');
            $bandSet[$bid] = true;
        }

        // Pásma v kanonickém pořadí (id vzestupně = 144 MHz → 122 GHz), jen přítomná.
        $bandMeta = EdiBand::query()
            ->whereIn('id', array_keys($bandSet))
            ->orderBy('id')
            ->get(['id', 'token', 'name']);

        // Kola s alespoň jedním pásmem (kolo bez dat by 100% sloupec rozbilo).
        /** @var list<array{name: string, year: int}> $rounds */
        $rounds = [];
        /** @var list<int> $roundIds */
        $roundIds = [];
        foreach ($kola as $k) {
            if (! isset($counts[$k->id])) {
                continue;
            }
            $rounds[] = ['name' => (string) $k->name, 'year' => (int) $k->starts_at->year];
            $roundIds[] = $k->id;
        }

        /** @var list<array{token: string, name: string}> $bands */
        $bands = [];
        /** @var list<list<int>> $stanice */
        $stanice = [];
        foreach ($bandMeta as $b) {
            $bands[] = ['token' => (string) $b->token, 'name' => (string) $b->name];
            $rada = [];
            foreach ($roundIds as $rid) {
                $rada[] = $counts[$rid][$b->id] ?? 0;
            }
            $stanice[] = $rada;
        }

        return ['rounds' => $rounds, 'bands' => $bands, 'stanice' => $stanice];
    }

    /** Atribut agregovaného řádku jako int (agregáty se vrací jako mixed). */
    private static function intAttr(Model $model, string $key): int
    {
        $value = $model->getAttribute($key);

        return is_numeric($value) ? (int) $value : 0;
    }
}
