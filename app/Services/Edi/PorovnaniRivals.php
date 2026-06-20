<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Http\Controllers\EdiPorovnaniController;
use App\Models\Edihead;
use App\Models\VkvpaData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Výběr soupeřů pro porovnání dvou deníků (stránka {@see EdiPorovnaniController}).
 *
 * Porovnat lze jen deníky z téhož kola a téže kategorie – soupeři se hledají
 * podle schválených záznamů výsledkové listiny ({@see VkvpaData}). Pravidlo
 * férovosti je shodné s vizualizací: soupeřův deník se vydá až po uzávěrce,
 * resp. vyhodnocení kola ({@see QsoGeometry::roundResultsDisclosable()}).
 *
 * Sdíleno s vizualizací a inkubátorem, které podle {@see hasRivals()}
 * rozhodují, zda na stránku porovnání vůbec odkázat.
 */
final class PorovnaniRivals
{
    public function __construct(private readonly QsoGeometry $geometry) {}

    /**
     * Soupeři pro porovnání: deníky z téhož kola a téže kategorie, seřazené
     * podle značky. Bez kola, před uzávěrkou nebo bez schváleného záznamu
     * s kategorií se nenabízí nic.
     *
     * @return EloquentCollection<int, Edihead>
     */
    public function rivals(Edihead $head): EloquentCollection
    {
        $query = $this->rivalEntriesQuery($head);

        if ($query === null) {
            return new EloquentCollection;
        }

        return Edihead::query()
            ->whereIn('id', $query->pluck('edihead_id'))
            ->where('id_kola', $head->id_kola)
            ->orderBy('p_call')
            ->get();
    }

    /**
     * Existuje aspoň jeden soupeř, se kterým jde deník porovnat? Levnější
     * varianta {@see rivals()} pro rozhodnutí, zda zobrazit odkaz na stránku
     * porovnání.
     */
    public function hasRivals(Edihead $head): bool
    {
        $query = $this->rivalEntriesQuery($head);

        return $query !== null
            && $query->whereHas('edihead', fn (Builder $q): Builder => $q->where('id_kola', $head->id_kola))->exists();
    }

    /**
     * Záznamy výsledkové listiny potenciálních soupeřů (schválené, totéž kolo
     * a kategorie, s EDI deníkem, kromě tohoto deníku). Null, když porovnání
     * není dostupné (bez kola, před uzávěrkou, bez záznamu s kategorií).
     *
     * @return Builder<VkvpaData>|null
     */
    private function rivalEntriesQuery(Edihead $head): ?Builder
    {
        if ($head->id_kola === null || ! $this->geometry->roundResultsDisclosable($head)) {
            return null;
        }

        $entry = VkvpaData::query()
            ->approved()
            ->where('edihead_id', $head->id)
            ->first(['id_kategorie']);

        if ($entry === null || $entry->id_kategorie === null) {
            return null;
        }

        return VkvpaData::query()
            ->approved()
            ->where('id_kola', $head->id_kola)
            ->where('id_kategorie', $entry->id_kategorie)
            ->whereNotNull('edihead_id')
            ->where('edihead_id', '!=', $head->id);
    }
}
