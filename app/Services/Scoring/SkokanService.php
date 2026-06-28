<?php

declare(strict_types=1);

namespace App\Services\Scoring;

use App\Models\EdiEntry;
use App\Models\EdiRound;
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
     * @param  Collection<int, EdiEntry>  $radky  řádky aktuálně zobrazovaného kola
     * @return array<int, array{delta: int|null, top: bool}> klíč = EdiEntry.id;
     *                                                       delta = null → první start v kategorii
     */
    public function bodyDeltas(EdiRound $kolo, Collection $radky): array
    {
        if ($radky->isEmpty()) {
            return [];
        }

        $znacky = $radky->pluck('callsign')->unique()->values()->all();

        // Předchozí (schválené) výsledky stejných značek z dřívějších kol, nejnovější první.
        $prior = EdiEntry::query()
            ->join('edi_rounds', 'edi_entries.round_id', '=', 'edi_rounds.id')
            ->where('edi_rounds.starts_at', '<', $kolo->starts_at->toDateString())
            ->where('edi_entries.approved', true)
            ->whereIn('edi_entries.callsign', $znacky)
            ->orderByDesc('edi_rounds.starts_at')
            ->get(['edi_entries.callsign', 'edi_entries.category_id', 'edi_entries.points']);

        // Poslední předchozí start podle (značka|kategorie) – díky řazení je první výskyt nejnovější.
        /** @var array<string, int> $prevBody */
        $prevBody = [];
        foreach ($prior as $p) {
            $prevBody[$p->callsign.'|'.$p->category_id] ??= (int) $p->points;
        }

        // Delta pro každý řádek + největší kladná delta v rámci kategorie.
        /** @var array<int, int|null> $delta */
        $delta = [];
        /** @var array<int, int> $maxByKat */
        $maxByKat = [];
        foreach ($radky as $r) {
            $key = $r->callsign.'|'.$r->category_id;
            if (! array_key_exists($key, $prevBody)) {
                $delta[$r->id] = null;

                continue;
            }

            $d = (int) $r->points - $prevBody[$key];
            $delta[$r->id] = $d;
            if ($d > 0) {
                // Záznam bez kategorie (category_id NULL) se sdružuje pod klíčem 0.
                $kat = $r->category_id ?? 0;
                $maxByKat[$kat] = max($maxByKat[$kat] ?? 0, $d);
            }
        }

        /** @var array<int, array{delta: int|null, top: bool}> $out */
        $out = [];
        foreach ($radky as $r) {
            $d = $delta[$r->id];
            $out[$r->id] = [
                'delta' => $d,
                'top' => $d !== null && $d > 0 && ($maxByKat[$r->category_id ?? 0] ?? 0) === $d,
            ];
        }

        return $out;
    }
}
