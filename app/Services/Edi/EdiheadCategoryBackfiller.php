<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\EdiCategory;
use App\Models\Edihead;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Hromadně naplní `edi_head.edi_category_id` zařazením přes {@see CategoryResolver}.
 *
 * Pro každý řádek se kategorie odvodí z `p_call` + `p_band` + `p_sect` úplně
 * stejně jako při importu (tentýž resolver), takže párování je 1:1 s importní
 * cestou. Řádky, jejichž pásmo/sekci nelze rozpoznat, zůstanou nezařazené
 * (`edi_category_id = NULL`) – sloupec je nullable a doplní je admin ručně.
 *
 * Navíc se hlídá konzistence se sloupci: `band`/`section` zařazené kategorie
 * musí odpovídat normalizovanému `p_band`/`p_sect`. Nesoulad (typicky rozbitý
 * číselník `edi_category`) se nasčítá do {@see BackfillReport::$mismatched} a
 * takový řádek se nezapíše.
 */
final class EdiheadCategoryBackfiller
{
    private const int CHUNK = 500;

    public function __construct(private readonly CategoryResolver $resolver) {}

    public function backfill(bool $dryRun = false): BackfillReport
    {
        $report = new BackfillReport;

        /** @var array<int, array{band: string, section: string}> $categories */
        $categories = EdiCategory::query()
            ->get(['id', 'band', 'section'])
            ->keyBy('id')
            ->map(static fn (EdiCategory $c): array => ['band' => $c->band, 'section' => $c->section])
            ->all();

        Edihead::query()
            ->select(['id', 'edi_category_id', 'p_call', 'p_band', 'p_sect'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($heads) use ($report, $categories, $dryRun): void {
                $updates = [];

                foreach ($heads as $head) {
                    $report->total++;

                    $id = $this->resolver->tryResolve($head->p_call, $head->p_band, $head->p_sect);

                    if ($id === null) {
                        $report->unresolved++;

                        continue;
                    }

                    // Pojistka: zařazená kategorie musí sedět na p_band/p_sect.
                    $cat = $categories[$id] ?? null;
                    if ($cat === null
                        || $cat['band'] !== $this->resolver->bandOrNull($head->p_band)
                        || $cat['section'] !== $this->resolver->sectionOrNull($head->p_sect)
                    ) {
                        $report->mismatched++;
                        $report->mismatchSample[$head->p_band.' | '.$head->p_sect] ??= (int) $head->id;

                        continue;
                    }

                    $report->resolved++;
                    if ($head->edi_category_id !== $id) {
                        $report->changed++;
                        $updates[$id][] = (int) $head->id;
                    }
                }

                if (! $dryRun) {
                    foreach ($updates as $catId => $headIds) {
                        DB::table('edi_head')->whereIn('id', $headIds)->update(['edi_category_id' => $catId]);
                    }
                }
            });

        return $report;
    }

    /**
     * Vynuluje `edi_head.edi_category_id` u propojených řádků, jejichž kategorie
     * (odvozená z hlavičky) se neshoduje s tím, v čem příspěvek reálně soutěží
     * (`vkvpa_data.id_kategorie`).
     *
     * Pravidlo: řádek se vynuluje, jen pokud JE propojený s nějakým `vkvpa_data`
     * a zároveň se jeho kategorie nerovná ANI JEDNOMU z těch příspěvků (jedna
     * hlavička může mít víc příspěvků). Osiřelé `edi_head` (bez příspěvku) se
     * nedotýkají – nemají s čím porovnat. Idempotentní.
     *
     * @return int počet vynulovaných řádků (v dry-run kolik BY se vynulovalo)
     */
    public function nullifyVkvpaDataConflicts(bool $dryRun = false): int
    {
        $query = DB::table('edi_head')
            ->whereNotNull('edi_head.edi_category_id')
            // je propojený alespoň s jedním příspěvkem
            ->whereExists(static fn (Builder $q): Builder => $q->from('vkvpa_data')
                ->whereColumn('vkvpa_data.edihead_id', 'edi_head.id'))
            // a žádný z těch příspěvků nemá shodnou kategorii
            ->whereNotExists(static fn (Builder $q): Builder => $q->from('vkvpa_data')
                ->whereColumn('vkvpa_data.edihead_id', 'edi_head.id')
                ->whereColumn('vkvpa_data.id_kategorie', 'edi_head.edi_category_id'));

        if ($dryRun) {
            return $query->count();
        }

        return $query->update(['edi_category_id' => null]);
    }
}
