<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
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

    public function test_nullify_clears_only_linked_heads_that_match_no_vkvpa_data(): void
    {
        $backfiller = app(EdiheadCategoryBackfiller::class);

        // hlavička 144 SO domestic = kategorie 1
        $conflict = $this->makeHead('OK1A', '144 MHz', 'SINGLE');   // → 1
        $match = $this->makeHead('OK1B', '144 MHz', 'SINGLE');      // → 1
        $orphan = $this->makeHead('OK1C', '144 MHz', 'SINGLE');     // → 1, bez příspěvku
        $multi = $this->makeHead('OK1D', '144 MHz', 'SINGLE');      // → 1, dva příspěvky (jeden sedí)

        $backfiller->backfill();

        // příspěvek s JINOU kategorií než hlavička (2 = 144 MO) → konflikt
        $this->vkvpaData($conflict->id, 2);
        // příspěvek se SHODNOU kategorií (1) → bez konfliktu
        $this->vkvpaData($match->id, 1);
        // dva příspěvky, jeden sedí (1) → hlavička se nemá nulovat
        $this->vkvpaData($multi->id, 2);
        $this->vkvpaData($multi->id, 1);

        $nulled = $backfiller->nullifyVkvpaDataConflicts();

        self::assertSame(1, $nulled);
        self::assertNull($conflict->refresh()->edi_category_id);   // vynulováno
        self::assertSame(1, $match->refresh()->edi_category_id);   // shoda → zůstává
        self::assertSame(1, $orphan->refresh()->edi_category_id);  // osiřelá → zůstává
        self::assertSame(1, $multi->refresh()->edi_category_id);   // jeden příspěvek sedí → zůstává

        // idempotence
        self::assertSame(0, $backfiller->nullifyVkvpaDataConflicts());
    }

    public function test_old_rounds_adopt_vkvpa_data_category_and_null_ambiguous(): void
    {
        $backfiller = app(EdiheadCategoryBackfiller::class);

        $old = $this->kolo('2025-06-01');   // < 2026 → autoritativní je příspěvek
        $new = $this->kolo('2026-06-01');   // >= 2026 → drží se hlavička

        // staré kolo, hlavička říká SINGLE (kat 1), ale příspěvek je multi (kat 2)
        $oldConflict = $this->makeHead('OK1A', '144 MHz', 'SINGLE');
        // staré kolo, hlavička nezařaditelná, příspěvek = kat 5
        $oldUnknown = $this->makeHead('OK1B', '1300 MHz', 'X');
        // staré kolo, jeden soubor do dvou kategorií → nejednoznačné → NULL
        $oldAmbiguous = $this->makeHead('OK1C', '144 MHz', 'SINGLE');
        // nové kolo, hlavička SINGLE (kat 1), příspěvek multi (kat 2) → drží hlavičku
        $newConflict = $this->makeHead('OK1D', '144 MHz', 'SINGLE');

        $backfiller->backfill();

        $this->vkvpaData($oldConflict->id, 2, $old->id);
        $this->vkvpaData($oldUnknown->id, 5, $old->id);
        $this->vkvpaData($oldAmbiguous->id, 1, $old->id);
        $this->vkvpaData($oldAmbiguous->id, 2, $old->id);
        $this->vkvpaData($newConflict->id, 2, $new->id);

        $backfiller->adoptVkvpaDataForOldRounds();

        self::assertSame(2, $oldConflict->refresh()->edi_category_id);   // převzato z příspěvku
        self::assertSame(5, $oldUnknown->refresh()->edi_category_id);    // doplněno z příspěvku
        self::assertNull($oldAmbiguous->refresh()->edi_category_id);     // nejednoznačné → NULL
        self::assertSame(1, $newConflict->refresh()->edi_category_id);   // nové kolo → hlavička (kat 1)

        // idempotence
        self::assertSame(0, $backfiller->adoptVkvpaDataForOldRounds());
    }

    private function kolo(string $datumKonani): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => $datumKonani.' 08:00:00',
            'datum_uzaverky' => $datumKonani.' 23:59:59',
            'nazev' => 'Test '.$datumKonani,
            'poznamka' => '',
        ]);
    }

    private function vkvpaData(int $edheadId, int $idKategorie, int $idKola = 1): void
    {
        VkvpaData::create([
            'id_kola' => $idKola,
            'edihead_id' => $edheadId,
            'id_kategorie' => $idKategorie,
            'znacka' => 'OK'.$edheadId, // unikátní v rámci (id_kola, id_kategorie)
        ]);
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
