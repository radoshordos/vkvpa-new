<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiVizualizaceController;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\User;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Vizualizační stránka deníku (mapa + grafy na jedné stránce).
 *
 * @see EdiVizualizaceController
 */
class EdiVizualizaceTest extends TestCase
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

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    public function test_anonymous_redirected_to_login(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.vizualizace', $head->id))
            ->assertRedirect(route('login'));
    }

    public function test_vizualizace_renders_for_logged_in_user(): void
    {
        $head = $this->importSample();

        $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->assertSee('Vizualizace deníku')
            ->assertSeeHtml('OK2KJT')
            ->assertSeeHtml('window.__vizConfig');
    }

    public function test_vizualizace_config_contains_points_and_squares(): void
    {
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->getContent() ?: '';

        // sample.edi: 2 QSO ve velkých čtvercích JN99 a JN89.
        $this->assertStringContainsString('OK2IMH', $html);
        $this->assertStringContainsString('OK2IWU', $html);
        $this->assertStringContainsString('JN99', $html);
        $this->assertStringContainsString('JN89', $html);
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
            ->get(route('edi.vizualizace', $head->id))
            ->assertForbidden();
    }

    public function test_round_stations_hidden_while_round_open(): void
    {
        // Aktivní kolo (příjem hlášení) → admin smí vizualizaci zobrazit,
        // ale vrstva roundStations musí být prázdná.
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => now()->addDays(7)->toDateTimeString(),
            'nazev' => '03/2026',
            'poznamka' => '',
            'aktivni' => true,
        ]);

        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));
        $head->update(['id_kola' => $kolo->id]);

        $html = $this->actingAs($this->admin())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('roundStations:[]', str_replace(' ', '', $html));
        $this->assertStringContainsString('Po vyhodnocen', $html);
    }

    public function test_round_stations_visible_after_round_evaluated(): void
    {
        // Vyhodnocené kolo → vrstva roundStations se smí zobrazit.
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026',
            'poznamka' => '',
            'vyhodnoceno' => '2026-03-21 10:00:00',
        ]);

        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));
        $head->update(['id_kola' => $kolo->id]);

        $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk();
    }

    public function test_compare_layer_renders_for_selected_rival(): void
    {
        // Vyhodnocené kolo, dva deníky: OK2IMH udělali oba, OK2IWU jen
        // OK2KJT (sample.edi), OK9ZZZ jen soupeř OK1BBB.
        [$head, $rival] = $this->seedEvaluatedRoundWithRival();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', ['head' => $head->id, 'porovnat' => $rival->id]))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('Porovnat s', $html);
        $this->assertStringContainsString('data-map-layer="porovnani"', $html);
        $this->assertStringContainsString('"rival":"OK1BBB"', $html);
        $this->assertStringContainsString('"onlyMine"', $html);
        $this->assertStringContainsString('OK9ZZZ', $html);
    }

    public function test_compare_select_shown_without_rival_selected(): void
    {
        [$head] = $this->seedEvaluatedRoundWithRival();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        // Výběr soupeře se nabízí, ale vrstva porovnání bez volby není.
        $this->assertStringContainsString('Porovnat s', $html);
        $this->assertStringContainsString('compare:null', str_replace(' ', '', $html));
        $this->assertStringNotContainsString('data-map-layer="porovnani"', $html);
    }

    public function test_compare_hidden_while_round_open(): void
    {
        // Kolo v příjmu hlášení → porovnání by odhalilo soupeřův deník;
        // query parametr se ignoruje a výběr se nenabízí (ani adminovi).
        [$head, $rival] = $this->seedEvaluatedRoundWithRival(aktivni: true);

        $html = $this->actingAs($this->admin())
            ->get(route('edi.vizualizace', ['head' => $head->id, 'porovnat' => $rival->id]))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('compare:null', str_replace(' ', '', $html));
        $this->assertStringNotContainsString('Porovnat s', $html);
        $this->assertStringNotContainsString('OK9ZZZ', $html);
    }

    /**
     * Kolo se dvěma deníky: sample.edi (OK2KJT) + soupeř OK1BBB. Soupeř má
     * společné QSO s OK2IMH a unikátní OK9ZZZ.
     *
     * @return array{Edihead, Edihead}
     */
    private function seedEvaluatedRoundWithRival(bool $aktivni = false): array
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026',
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
