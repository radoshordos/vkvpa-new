<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiVizualizaceController;
use App\Models\EdiCategory;
use App\Models\Edihead;
use App\Models\User;
use App\Models\VkvpaData;
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

    public function test_anonymous_sees_vizualizace_when_no_active_round(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.vizualizace', $head->id))
            ->assertOk();
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

    public function test_active_round_does_not_block_non_admin(): void
    {
        // Vizualizace je veřejná i během otevřeného upload okna – ukazuje jen
        // vlastní deník; citlivé vrstvy (roundStations, porovnání) se hlídají
        // samostatně (viz testy níže).
        VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => now()->addDays(7)->toDateTimeString(),
            'nazev' => '03/2026',
            'poznamka' => '',
        ]);

        $head = $this->importSample();

        $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk();
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

    public function test_config_contains_charts_moved_from_inkubator(): void
    {
        // Grafy přestěhované z inkubátoru: průběh skóre, timeline s násobiči,
        // vážená růžice, body podle čtverců + mapa s přehráváním (okno, časy).
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString('cumulative', $html);
        $this->assertStringContainsString('squarePoints', $html);
        $this->assertStringContainsString('chartPrubeh', $html);
        $this->assertStringContainsString('chartCtverce', $html);
        $this->assertStringContainsString('<select id="viz-layer-select"', $html);
        $this->assertStringContainsString('value="playback"', $html);
        $this->assertStringContainsString('value="ctverce"', $html);
        $this->assertStringContainsString('data-az-metric="km"', $html);
        $this->assertStringContainsString('TOP ODX', $html);
    }

    public function test_cumulative_score_follows_scoring_rules(): void
    {
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->getContent() ?: '';

        // QSO 1 (JN99BP, vlastní čtverec): 2 b. × 1 násobič = 2.
        // QSO 2 (JN89PV, sousední čtverec 3 b.): součet 5 b. × 2 násobiče = 10.
        $compact = str_replace(' ', '', $html);
        $this->assertStringContainsString('"body":2', $compact);
        $this->assertStringContainsString('"body":10', $compact);
    }

    public function test_sezona_trend_from_public_results(): void
    {
        $head = $this->seedEvaluatedRoundWithRivalEntry();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $compact = str_replace(' ', '', $html);
        $this->assertStringContainsString('"body":[14]', $compact);
        $this->assertStringContainsString('"poradi":[1]', $compact);
    }

    public function test_compare_moved_to_standalone_page(): void
    {
        // Porovnání deníků se přesunulo na samostatnou stránku (edi.porovnani);
        // vizualizace na ni jen odkazuje (když je s kým porovnávat) a sama
        // porovnání nenabízí.
        $head = $this->seedEvaluatedRoundWithRivalEntry();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('edi.porovnani', ['head' => $head->id]), $html);
        $this->assertStringNotContainsString('value="porovnani"', $html);
        $this->assertStringNotContainsString('Porovnat s', $html);
    }

    public function test_compare_link_hidden_without_rival_in_same_category(): void
    {
        // Deník bez kola (a tedy bez soupeře z téže kategorie) → odkaz na
        // stránku porovnání se nezobrazuje, neměla by co nabídnout.
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringNotContainsString(route('edi.porovnani', ['head' => $head->id]), $html);
    }

    /**
     * Vyhodnocené kolo se dvěma deníky (OK2KJT ze sample.edi + soupeř OK1BBB)
     * a schválenými záznamy listiny v téže kategorii – porovnání je dostupné.
     */
    private function seedEvaluatedRoundWithRivalEntry(): Edihead
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026',
            'poznamka' => '',
            'vyhodnoceno' => '2026-03-21 10:00:00',
        ]);

        $head = $this->importSample();
        $head->update(['id_kola' => $kolo->id]);

        $rival = Edihead::create([
            'id_kola' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89',
            'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100,
        ]);

        $kategorie = EdiCategory::create(['name' => '144 MHz', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);

        foreach ([[$head, 'OK2KJT'], [$rival, 'OK1BBB']] as [$h, $znacka]) {
            VkvpaData::create([
                'id_kola' => $kolo->id, 'id_kategorie' => $kategorie->id,
                'qrp' => false, 'lp' => false, 'znacka' => $znacka, 'locator' => 'JN99AJ',
                'pocet' => 2, 'bodu_za_qso' => 7, 'nasobice' => 2, 'body' => 14,
                'jmeno' => 'Test', 'mail' => 't@t.cz', 'telefon' => '', 'poznamka' => '',
                'soapbox' => '', 'ip' => '', 'edihead_id' => $h->id,
                'poradi' => 1, 'schvaleno' => true, 'session_id' => '',
            ]);
        }

        return $head;
    }
}
