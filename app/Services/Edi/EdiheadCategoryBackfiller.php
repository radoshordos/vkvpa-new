<?php

declare(strict_types=1);

namespace App\Services\Edi;

use App\Models\EdiCategory;
use App\Models\Edihead;
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
}
