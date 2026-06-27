<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * edi_head.edi_category_id se nastavuje 1:1 z vkvpa_data.id_kategorie.
 * Osiřelé (bez příspěvku) i víceznačné (víc kategorií) deníky → NULL.
 *
 * @see EdiheadCategoryBackfiller
 */
class EdiheadCategoryMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_mirrors_vkvpa_data_category_and_nulls_orphans_and_ambiguous(): void
    {
        $single = $this->makeHead('OK1A');     // jeden příspěvek → jeho kategorie
        $orphan = $this->makeHead('OK1B');     // bez příspěvku → NULL
        $ambiguous = $this->makeHead('OK1C');  // dva příspěvky, různé kategorie → NULL
        $stale = $this->makeHead('OK1D', 9);   // měl chybnou hodnotu → přepíše se

        $this->vkvpaData($single->id, 3);
        $this->vkvpaData($ambiguous->id, 1);
        $this->vkvpaData($ambiguous->id, 2);
        $this->vkvpaData($stale->id, 5);

        $changed = app(EdiheadCategoryBackfiller::class)->mirrorVkvpaDataCategory();

        self::assertSame(3, $single->refresh()->edi_category_id);
        self::assertNull($orphan->refresh()->edi_category_id);
        self::assertNull($ambiguous->refresh()->edi_category_id);
        self::assertSame(5, $stale->refresh()->edi_category_id);

        // single(set) + ambiguous(no-op, už NULL) + stale(9→5) = 2 změny; orphan už NULL
        self::assertSame(2, $changed);

        // idempotence
        self::assertSame(0, app(EdiheadCategoryBackfiller::class)->mirrorVkvpaDataCategory());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $h = $this->makeHead('OK1A');
        $this->vkvpaData($h->id, 4);

        $n = app(EdiheadCategoryBackfiller::class)->mirrorVkvpaDataCategory(dryRun: true);

        self::assertSame(1, $n);
        self::assertNull($h->refresh()->edi_category_id);
    }

    private function vkvpaData(int $edheadId, int $idKategorie): void
    {
        VkvpaData::create([
            'id_kola' => 1,
            'edihead_id' => $edheadId,
            'id_kategorie' => $idKategorie,
            'znacka' => 'OK'.$edheadId, // unikátní v rámci (id_kola, id_kategorie)
        ]);
    }

    private function makeHead(string $pCall, ?int $ediCategoryId = null): Edihead
    {
        return Edihead::create([
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
