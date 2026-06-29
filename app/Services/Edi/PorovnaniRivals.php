<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Http\Controllers\EdiPorovnaniController;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Výběr soupeřů pro porovnání dvou deníků (stránka {@see EdiPorovnaniController}).
 *
 * Porovnat lze jen deníky z téhož kola a téže kategorie – soupeři se hledají
 * podle schválených záznamů výsledkové listiny ({@see EdiEntry}). Pravidlo
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
     * @return EloquentCollection<int, EdiHead>
     */
    public function rivals(EdiHead $head): EloquentCollection
    {
        $query = $this->rivalEntriesQuery($head);

        if ($query === null) {
            return new EloquentCollection;
        }

        return EdiHead::query()
            ->whereIn('id', $query->pluck('edi_head_id'))
            ->where('round_id', $head->round_id)
            ->orderBy('p_call')
            ->get();
    }

    /**
     * Existuje aspoň jeden soupeř, se kterým jde deník porovnat? Levnější
     * varianta {@see rivals()} pro rozhodnutí, zda zobrazit odkaz na stránku
     * porovnání.
     */
    public function hasRivals(EdiHead $head): bool
    {
        $query = $this->rivalEntriesQuery($head);

        return $query !== null
            && $query->whereHas('ediHead', fn (Builder $q): Builder => $q->where('round_id', $head->round_id))->exists();
    }

    /**
     * Záznamy výsledkové listiny potenciálních soupeřů (schválené, totéž kolo
     * a kategorie, s EDI deníkem, kromě tohoto deníku). Null, když porovnání
     * není dostupné (bez kola, před uzávěrkou, bez záznamu s kategorií).
     *
     * @return Builder<EdiEntry>|null
     */
    private function rivalEntriesQuery(EdiHead $head): ?Builder
    {
        if ($head->round_id === null || ! $this->geometry->roundResultsDisclosable($head)) {
            return null;
        }

        $entry = EdiEntry::query()
            ->approved()
            ->where('edi_head_id', $head->id)
            ->first(['category_id']);

        if ($entry === null || $entry->category_id === null) {
            return null;
        }

        return EdiEntry::query()
            ->approved()
            ->where('round_id', $head->round_id)
            ->where('category_id', $entry->category_id)
            ->whereNotNull('edi_head_id')
            ->where('edi_head_id', '!=', $head->id);
    }
}
