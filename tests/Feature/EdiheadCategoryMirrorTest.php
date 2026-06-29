<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * edi_heads.edi_category_id se nastavuje 1:1 z edi_entries.category_id.
 * Osiřelé (bez příspěvku) i víceznačné (víc kategorií) deníky → NULL.
 *
 * @see EdiheadCategoryBackfiller
 */
class EdiheadCategoryMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_mirrors_edi_entries_category_and_nulls_orphans_and_ambiguous(): void
    {
        $single = $this->makeHead('OK1A');     // jeden příspěvek → jeho kategorie
        $orphan = $this->makeHead('OK1B');     // bez příspěvku → NULL
        $ambiguous = $this->makeHead('OK1C');  // dva příspěvky, různé kategorie → NULL
        $stale = $this->makeHead('OK1D', 9);   // měl chybnou hodnotu → přepíše se

        $this->vkvpaData($single->id, 3);
        $this->vkvpaData($ambiguous->id, 1);
        $this->vkvpaData($ambiguous->id, 2);
        $this->vkvpaData($stale->id, 5);

        $changed = app(EdiheadCategoryBackfiller::class)->mirrorEdiEntryCategory();

        self::assertSame(3, $single->refresh()->edi_category_id);
        self::assertNull($orphan->refresh()->edi_category_id);
        self::assertNull($ambiguous->refresh()->edi_category_id);
        self::assertSame(5, $stale->refresh()->edi_category_id);

        // single(set) + ambiguous(no-op, už NULL) + stale(9→5) = 2 změny; orphan už NULL
        self::assertSame(2, $changed);

        // idempotence
        self::assertSame(0, app(EdiheadCategoryBackfiller::class)->mirrorEdiEntryCategory());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $h = $this->makeHead('OK1A');
        $this->vkvpaData($h->id, 4);

        $n = app(EdiheadCategoryBackfiller::class)->mirrorEdiEntryCategory(dryRun: true);

        self::assertSame(1, $n);
        self::assertNull($h->refresh()->edi_category_id);
    }

    private function vkvpaData(int $edheadId, int $idKategorie): void
    {
        EdiEntry::create([
            'round_id' => 1,
            'edi_head_id' => $edheadId,
            'category_id' => $idKategorie,
            'callsign' => 'OK'.$edheadId, // unikátní v rámci (round_id, category_id)
        ]);
    }

    private function makeHead(string $pCall, ?int $ediCategoryId = null): EdiHead
    {
        return EdiHead::create([
            'edi_category_id' => $ediCategoryId,
            't_date' => '20240101;20240101',
            'p_call' => $pCall,
            'p_wwlo' => 'JN69',
            'p_sect' => 'SINGLE',
            'p_band' => '144 MHz',
            'r_name' => 'Test',
            'r_phon' => '',
            's_powe' => 100,
        ]);
    }
}
