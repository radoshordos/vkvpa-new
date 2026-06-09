<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MapController;
use App\Models\Edihead;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Čtyři mapové pohledy: M (ježek), N (špendlíky), S (velké čtverce), C (CRK).
 *
 * @see MapController
 */
class MapTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    public function test_jezek_map_renders(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.mapa.jezek', $head->ID))
            ->assertOk()
            ->assertSeeHtml('Mapa spojení')
            ->assertSeeHtml('OK2KJT')
            ->assertSeeHtml('window.__mapConfig')
            ->assertSee('ježek');
    }

    public function test_spendliky_map_renders(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.mapa.spendliky', $head->ID))
            ->assertOk()
            ->assertSee('špendlíky')
            ->assertSeeHtml('OK2KJT');
    }

    public function test_lokatory_map_renders_with_big_squares(): void
    {
        $head = $this->importSample();

        // sample.edi má QSO ve velkých čtvercích JN99 a JN89.
        $this->get(route('edi.mapa.lokatory', $head->ID))
            ->assertOk()
            ->assertSee('velké čtverce')
            ->assertSee('JN99')
            ->assertSee('JN89');
    }

    public function test_crk_map_renders_with_combined_data(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.mapa.crk', $head->ID))
            ->assertOk()
            ->assertSee('kombinovaná mapa')
            ->assertSeeHtml('OK2KJT')
            ->assertSeeHtml('OK2IMH')
            ->assertSeeHtml('roundStations')
            ->assertSeeHtml('window.__mapConfig');
    }

    public function test_crk_map_shows_pending_note_while_round_open(): void
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15', 'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026', 'poznamka' => '', 'aktivni' => true,
        ]);
        $head = Edihead::create(['id_kola' => $kolo->id, 'TDate' => '20260315', 'PCall' => 'OK1AAA', 'PWWLo' => 'JN99', 'PBand' => '144 MHz', 'RName' => 'A', 'RHBBS' => 'a@a.cz', 'SPowe' => 100]);

        $this->get(route('edi.mapa.crk', $head->ID))
            ->assertOk()
            ->assertSee('Po vyhodnocení kola');
    }

    public function test_crk_map_hides_pending_note_after_evaluation(): void
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15', 'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026', 'poznamka' => '', 'vyhodnoceno' => '2026-03-21 10:00:00',
        ]);
        $head = Edihead::create(['id_kola' => $kolo->id, 'TDate' => '20260315', 'PCall' => 'OK1AAA', 'PWWLo' => 'JN99', 'PBand' => '144 MHz', 'RName' => 'A', 'RHBBS' => 'a@a.cz', 'SPowe' => 100]);

        $this->get(route('edi.mapa.crk', $head->ID))
            ->assertOk()
            ->assertDontSee('Po vyhodnocení kola');
    }
}
