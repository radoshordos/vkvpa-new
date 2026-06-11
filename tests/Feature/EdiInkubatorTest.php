<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiInkubatorController;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Vizuální inkubátor – experimentální vizualizace deníku.
 *
 * @see EdiInkubatorController
 */
class EdiInkubatorTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    private function user(): User
    {
        return User::create(['name' => 'Test', 'password' => Hash::make('x'), 'is_admin' => false]);
    }

    public function test_anonymous_redirected_to_login(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.inkubator', $head->id))
            ->assertRedirect(route('login'));
    }

    public function test_inkubator_renders_for_logged_in_user(): void
    {
        $head = $this->importSample();

        $this->actingAs($this->user())
            ->get(route('edi.inkubator', $head->id))
            ->assertOk()
            ->assertSee('Vizuální inkubátor')
            ->assertSeeHtml('OK2KJT');
    }

    public function test_tables_contain_odx_and_multipliers(): void
    {
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.inkubator', $head->id))
            ->getContent() ?: '';

        // sample.edi: 2 QSO (OK2IMH v JN99, OK2IWU v JN89).
        $this->assertStringContainsString('OK2IMH', $html);
        $this->assertStringContainsString('OK2IWU', $html);
        $this->assertStringContainsString('TOP ODX', $html);
        // Nový násobič JN89 (vlastní čtverec JN99 je násobič č. 1 automaticky).
        $this->assertStringContainsString('JN89', $html);
    }

    public function test_charts_moved_to_vizualizace_page(): void
    {
        // Grafy a mapa s přehráváním se přestěhovaly na stránku Vizualizace –
        // inkubátor zůstává jen u tabulek a na vizualizaci odkazuje.
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.inkubator', $head->id))
            ->getContent() ?: '';

        $this->assertStringContainsString(route('edi.vizualizace', ['head' => $head->id]), $html);
        $this->assertStringNotContainsString('window.__inkubatorConfig', $html);
        $this->assertStringNotContainsString('chartPrubeh', $html);
        $this->assertStringNotContainsString('ink-mapa', $html);
    }

    public function test_active_round_blocks_non_admin(): void
    {
        VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => now()->addDays(7)->toDateTimeString(),
            'nazev' => '03/2026',
            'poznamka' => '',
            'aktivni' => true,
        ]);

        $head = $this->importSample();

        $this->actingAs($this->user())
            ->get(route('edi.inkubator', $head->id))
            ->assertForbidden();
    }

    public function test_compare_moved_to_standalone_page(): void
    {
        // Porovnání průběhu se soupeřem se přesunulo na samostatnou stránku
        // (edi.porovnani); inkubátor na ni jen odkazuje (když je s kým
        // porovnávat – soupeř v téže kategorii) a výběr nenabízí.
        [$head, $rival] = $this->seedEvaluatedRoundWithRival();

        $kategorie = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);
        foreach ([[$head, 'OK2KJT'], [$rival, 'OK1BBB']] as [$h, $znacka]) {
            VkvpaData::create([
                'id_kola' => $head->id_kola, 'id_kategorie' => $kategorie->id,
                'qrp' => false, 'lp' => false, 'znacka' => $znacka, 'locator' => 'JN99AJ',
                'pocet' => 2, 'bodu_za_qso' => 7, 'nasobice' => 2, 'body' => 14,
                'jmeno' => 'Test', 'mail' => 't@t.cz', 'telefon' => '', 'poznamka' => '',
                'soapbox' => '', 'ip' => '', 'edihead_id' => $h->id,
                'poradi' => 1, 'schvaleno' => true, 'session_id' => '',
            ]);
        }

        $html = $this->actingAs($this->user())
            ->get(route('edi.inkubator', ['head' => $head->id, 'porovnat' => $rival->id]))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('edi.porovnani', ['head' => $head->id]), $html);
        $this->assertStringNotContainsString('Porovnat průběh s', $html);
        $this->assertStringNotContainsString('rivalCumulative', $html);
    }

    public function test_compare_link_hidden_without_rival_in_same_category(): void
    {
        // Soupeř existuje, ale bez schváleného záznamu listiny v téže
        // kategorii → odkaz na stránku porovnání se nezobrazuje.
        [$head] = $this->seedEvaluatedRoundWithRival();

        $html = $this->actingAs($this->user())
            ->get(route('edi.inkubator', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringNotContainsString(route('edi.porovnani', ['head' => $head->id]), $html);
    }

    /**
     * Vyhodnocené kolo se dvěma deníky: sample.edi (OK2KJT) + soupeř OK1BBB.
     *
     * @return array{Edihead, Edihead}
     */
    private function seedEvaluatedRoundWithRival(bool $aktivni = false): array
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '3. kolo 2026',
            'poznamka' => '',
            'aktivni' => $aktivni,
            'vyhodnoceno' => $aktivni ? null : '2026-03-21 10:00:00',
        ]);

        $head = $this->importSample();
        $head->update(['id_kola' => $kolo->id]);

        $rival = Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89',
            'p_band' => '144 MHz', 'r_name' => 'B', 'r_hbbs' => 'b@b.cz', 's_powe' => 100,
        ]);
        Ediline::create(['edihead_id' => $rival->id, 'date' => '260315', 'time' => '0830', 'call_sign' => 'OK2IMH', 'received_wwl' => 'JN99BP']);
        Ediline::create(['edihead_id' => $rival->id, 'date' => '260315', 'time' => '0831', 'call_sign' => 'OK9ZZZ', 'received_wwl' => 'JO60AA']);

        return [$head, $rival];
    }
}
