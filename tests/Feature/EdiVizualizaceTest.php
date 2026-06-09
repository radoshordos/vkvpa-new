<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiVizualizaceController;
use App\Models\Edihead;
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
}
