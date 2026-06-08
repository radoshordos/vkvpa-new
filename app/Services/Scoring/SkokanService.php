<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Support\Collection;

/**
 * „Skokan" – porovnání výsledku závodníka s jeho posledním předchozím startem.
 *
 * Body se po uložení deníku už nemění (mění se jen pořadí), takže body-delta
 * je k dispozici i během živého upload okna. Porovnává se vždy v rámci stejné
 * kategorie a proti poslednímu kolu, kde daná značka startovala (vynechaná kola
 * se přeskočí). Pořadí-delta (posun v žebříčku) má smysl až po uzávěrce – to
 * tato služba zatím neřeší.
 */
final class SkokanService
{
    /**
     * Body-delta a příznak největšího skokana pro řádky zobrazovaného kola.
     *
     * @param  Collection<int, VkvpaData>  $radky  řádky aktuálně zobrazovaného kola
     * @return array<int, array{delta: int|null, top: bool}> klíč = VkvpaData.id;
     *                                                       delta = null → první start v kategorii
     */
    public function bodyDeltas(VkvpaKola $kolo, Collection $radky): array
    {
        if ($radky->isEmpty()) {
            return [];
        }

        $znacky = $radky->pluck('znacka')->unique()->values()->all();

        // Předchozí (schválené) výsledky stejných značek z dřívějších kol, nejnovější první.
        $prior = VkvpaData::query()
            ->join('vkvpa_kola', 'vkvpa_data.id_kola', '=', 'vkvpa_kola.id')
            ->where('vkvpa_kola.datum_konani', '<', $kolo->datum_konani->toDateString())
            ->where('vkvpa_data.schvaleno', true)
            ->whereIn('vkvpa_data.znacka', $znacky)
            ->orderByDesc('vkvpa_kola.datum_konani')
            ->get(['vkvpa_data.znacka', 'vkvpa_data.id_kategorie', 'vkvpa_data.body']);

        // Poslední předchozí start podle (značka|kategorie) – díky řazení je první výskyt nejnovější.
        /** @var array<string, int> $prevBody */
        $prevBody = [];
        foreach ($prior as $p) {
            $prevBody[$p->znacka.'|'.$p->id_kategorie] ??= (int) $p->body;
        }

        // Delta pro každý řádek + největší kladná delta v rámci kategorie.
        /** @var array<int, int|null> $delta */
        $delta = [];
        /** @var array<int, int> $maxByKat */
        $maxByKat = [];
        foreach ($radky as $r) {
            $key = $r->znacka.'|'.$r->id_kategorie;
            if (! array_key_exists($key, $prevBody)) {
                $delta[$r->id] = null;

                continue;
            }

            $d = (int) $r->body - $prevBody[$key];
            $delta[$r->id] = $d;
            if ($d > 0) {
                $maxByKat[$r->id_kategorie] = max($maxByKat[$r->id_kategorie] ?? 0, $d);
            }
        }

        /** @var array<int, array{delta: int|null, top: bool}> $out */
        $out = [];
        foreach ($radky as $r) {
            $d = $delta[$r->id];
            $out[$r->id] = [
                'delta' => $d,
                'top' => $d !== null && $d > 0 && ($maxByKat[$r->id_kategorie] ?? 0) === $d,
            ];
        }

        return $out;
    }
}
