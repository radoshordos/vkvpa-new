<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Services\Edi\CategoryResolver;
use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backfill `edi_head.edi_category_id` musí zařadit shodně s {@see CategoryResolver}
 * (tj. 1:1 s importní cestou) a jen pro řádky, jejichž band/section sedí na
 * p_band/p_sect. Nerozpoznané deníky zůstávají NULL.
 *
 * @see EdiheadCategoryBackfiller
 */
class EdiheadCategoryBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_matches_resolver_one_to_one_and_leaves_unknown_null(): void
    {
        $resolver = new CategoryResolver;

        // řádky se zařaditelnou i nezařaditelnou hlavičkou
        $ok = $this->makeHead('OK1A', '144 MHz', 'SINGLE');     // → 144 SO domestic
        $dx = $this->makeHead('9A1A', '435 MHz', 'MULTI-OP');   // → 432 MO dx
        $loose = $this->makeHead('OK1B', '144MHz', ' single');  // tolerantní normalizace
        $badBand = $this->makeHead('OK1C', '1300 MHz', 'SO');   // nerozpoznané pásmo → NULL
        $badSect = $this->makeHead('OK1D', '144 MHz', '01');    // nerozpoznaná sekce → NULL

        $report = app(EdiheadCategoryBackfiller::class)->backfill();

        self::assertSame(5, $report->total);
        self::assertSame(3, $report->resolved);
        self::assertSame(3, $report->changed);
        self::assertSame(2, $report->unresolved);
        self::assertSame(0, $report->mismatched);

        // 1:1 shoda s resolverem
        foreach ([$ok, $dx, $loose, $badBand, $badSect] as $h) {
            $h->refresh();
            self::assertSame(
                $resolver->tryResolve($h->p_call, $h->p_band, $h->p_sect),
                $h->edi_category_id,
                "Backfill se rozešel s resolverem pro {$h->p_call}/{$h->p_band}/{$h->p_sect}",
            );
        }

        self::assertNull($badBand->edi_category_id);
        self::assertNull($badSect->edi_category_id);
    }

    public function test_backfill_is_idempotent_and_dry_run_writes_nothing(): void
    {
        $h = $this->makeHead('OK1A', '144 MHz', 'SINGLE');

        $dry = app(EdiheadCategoryBackfiller::class)->backfill(dryRun: true);
        self::assertSame(1, $dry->changed);
        self::assertNull($h->refresh()->edi_category_id); // dry-run nezapsal

        app(EdiheadCategoryBackfiller::class)->backfill();
        self::assertNotNull($h->refresh()->edi_category_id);

        // druhý běh už nic nemění
        $second = app(EdiheadCategoryBackfiller::class)->backfill();
        self::assertSame(0, $second->changed);
        self::assertSame(1, $second->resolved);
    }

    private function makeHead(string $pCall, string $pBand, string $pSect): Edihead
    {
        return Edihead::create([
            't_date' => '20240101;20240101',
            'p_call' => $pCall,
            'p_wwlo' => 'JN69',
            'p_sect' => $pSect,
            'p_band' => $pBand,
            'r_name' => 'Test',
            'r_phon' => '',
            's_powe' => 100,
        ]);
    }
}
