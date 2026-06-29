<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiVizualizaceController;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Models\EdiRound;
use App\Models\User;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Vizualizační stránka deníku (mapa + grafy na jedné stránce).
 *
 * @see EdiVizualizaceController
 */
class EdiVizualizaceTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): EdiHead
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

    public function test_vizualizace_links_to_original_edi_file(): void
    {
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertStringContainsString(route('edi.soubor', ['head' => $head->id]), $html);
    }

    public function test_active_round_does_not_block_non_admin(): void
    {
        // Vizualizace je veřejná i během otevřeného upload okna – ukazuje jen
        // vlastní deník; citlivé vrstvy (roundStations, porovnání) se hlídají
        // samostatně (viz testy níže).
        EdiRound::create([
            'starts_at' => '2026-03-15',
            'closes_at' => now()->addDays(7)->toDateTimeString(),
            'name' => '03/2026',
            'note' => '',
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
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15',
            'closes_at' => now()->addDays(7)->toDateTimeString(),
            'name' => '03/2026',
            'note' => '',
        ]);

        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));
        $head->update(['round_id' => $kolo->id]);

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
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15',
            'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026',
            'note' => '',
            'evaluated_at' => '2026-03-21 10:00:00',
        ]);

        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $head = new EdiImportService()->import(new EdiParser()->parse($edi));
        $head->update(['round_id' => $kolo->id]);

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

    public function test_sezona_trend_uses_round_start_year_not_round_name(): void
    {
        $kategorie = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);
        $round2026 = EdiRound::create([
            'starts_at' => '2026-01-18',
            'closes_at' => '2026-01-23 23:59:59',
            'name' => '01/2025',
            'note' => '',
            'evaluated_at' => '2026-01-24 10:00:00',
        ]);
        $round2025 = EdiRound::create([
            'starts_at' => '2025-12-21',
            'closes_at' => '2025-12-26 23:59:59',
            'name' => '12/2026',
            'note' => '',
            'evaluated_at' => '2025-12-27 10:00:00',
        ]);

        $head = EdiHead::create([
            'round_id' => $round2026->id,
            't_date' => '20260118',
            'p_call' => 'OK1AAA',
            'p_wwlo' => 'JN79',
            'p_band' => '144 MHz',
            'r_name' => 'A',
            'r_emai' => 'a@a.cz',
            's_powe' => 100,
        ]);

        $createEntry = function (EdiRound $kolo, int $body, ?int $headId) use ($kategorie): void {
            EdiEntry::create([
                'round_id' => $kolo->id,
                'category_id' => $kategorie->id,
                'qrp' => false,
                'lp' => false,
                'callsign' => 'OK1AAA',
                'locator' => 'JN79AA',
                'qso_count' => 1,
                'qso_points' => 1,
                'multiplier' => 1,
                'points' => $body,
                'name' => 'Test',
                'email' => 't@t.cz',
                'phone' => '',
                'note' => '',
                'soapbox' => '',
                'ip' => '',
                'edi_head_id' => $headId,
                'rank' => 1,
                'approved' => true,
                'session_id' => '',
            ]);
        };
        $createEntry($round2026, 100, $head->id);
        $createEntry($round2025, 999, null);

        $sezona = app(DenikStatistiky::class)->sezona($head);

        if ($sezona === null) {
            $this->fail('Expected season statistics for the seeded entry.');
        }

        $this->assertSame(['01/2025'], $sezona['labels']);
        $this->assertSame([100], $sezona['body']);
        $this->assertSame([1], $sezona['poradi']);
    }

    public function test_sezona_trend_filters_by_deniks_category(): void
    {
        $catA = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);
        $catB = EdiCategory::create(['name' => '432 MHz', 'section' => 'SO', 'variant' => 'domestic']);

        $round1 = EdiRound::create([
            'starts_at' => '2026-01-18', 'closes_at' => '2026-01-23 23:59:59',
            'name' => '01', 'note' => '', 'evaluated_at' => '2026-01-24 10:00:00',
        ]);
        $round2 = EdiRound::create([
            'starts_at' => '2026-02-15', 'closes_at' => '2026-02-20 23:59:59',
            'name' => '02', 'note' => '', 'evaluated_at' => '2026-02-21 10:00:00',
        ]);

        $head = EdiHead::create([
            'round_id' => $round1->id, 't_date' => '20260118', 'p_call' => 'OK1AAA', 'p_wwlo' => 'JN79',
            'p_band' => '144 MHz', 'r_name' => 'A', 'r_emai' => 'a@a.cz', 's_powe' => 100,
        ]);

        $createEntry = function (EdiRound $kolo, EdiCategory $cat, int $body, int $rank, ?int $headId): void {
            EdiEntry::create([
                'round_id' => $kolo->id, 'category_id' => $cat->id,
                'qrp' => false, 'lp' => false, 'callsign' => 'OK1AAA', 'locator' => 'JN79AA',
                'qso_count' => 1, 'qso_points' => 1, 'multiplier' => 1, 'points' => $body,
                'name' => 'Test', 'email' => 't@t.cz', 'phone' => '', 'note' => '',
                'soapbox' => '', 'ip' => '', 'edi_head_id' => $headId,
                'rank' => $rank, 'approved' => true, 'session_id' => '',
            ]);
        };

        // Deník patří do kategorie A. V kole 2 jela stanice v obou kategoriích –
        // trend musí vzít záznam kategorie A (body 200/pořadí 2), nikoli cizí
        // kategorii B (body 999/pořadí 1), která má vyšší id a dřív „vyhrávala".
        $createEntry($round1, $catA, 100, 1, $head->id);
        $createEntry($round2, $catA, 200, 2, null);
        $createEntry($round2, $catB, 999, 1, null);

        $sezona = app(DenikStatistiky::class)->sezona($head);

        $this->assertNotNull($sezona);
        $this->assertSame(['01', '02'], $sezona['labels']);
        $this->assertSame([100, 200], $sezona['body']);
        $this->assertSame([1, 2], $sezona['poradi']);
    }

    public function test_sezona_trend_hidden_when_denik_has_no_category(): void
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-01-18', 'closes_at' => '2026-01-23 23:59:59',
            'name' => '01', 'note' => '', 'evaluated_at' => '2026-01-24 10:00:00',
        ]);

        $head = EdiHead::create([
            'round_id' => $kolo->id, 't_date' => '20260118', 'p_call' => 'OK1AAA', 'p_wwlo' => 'JN79',
            'p_band' => '144 MHz', 'r_name' => 'A', 'r_emai' => 'a@a.cz', 's_powe' => 100,
        ]);

        EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => null,
            'qrp' => false, 'lp' => false, 'callsign' => 'OK1AAA', 'locator' => 'JN79AA',
            'qso_count' => 1, 'qso_points' => 1, 'multiplier' => 1, 'points' => 100,
            'name' => 'Test', 'email' => 't@t.cz', 'phone' => '', 'note' => '',
            'soapbox' => '', 'ip' => '', 'edi_head_id' => $head->id,
            'rank' => 1, 'approved' => true, 'session_id' => '',
        ]);

        $this->assertNull(app(DenikStatistiky::class)->sezona($head));
    }

    public function test_removed_inkubator_route_is_not_linked_from_vizualizace(): void
    {
        $head = $this->importSample();

        $html = $this->actingAs($this->user())
            ->get(route('edi.vizualizace', $head->id))
            ->assertOk()
            ->getContent() ?: '';

        $this->assertFalse(Route::has('edi.stat.inkubator'));
        $this->assertStringNotContainsString('statistiky-inkubator', $html);
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
    private function seedEvaluatedRoundWithRivalEntry(): EdiHead
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15',
            'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026',
            'note' => '',
            'evaluated_at' => '2026-03-21 10:00:00',
        ]);

        $head = $this->importSample();
        $head->update(['round_id' => $kolo->id]);

        $rival = EdiHead::create([
            'round_id' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89',
            'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100,
        ]);

        $kategorie = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);

        foreach ([[$head, 'OK2KJT'], [$rival, 'OK1BBB']] as [$h, $znacka]) {
            EdiEntry::create([
                'round_id' => $kolo->id, 'category_id' => $kategorie->id,
                'qrp' => false, 'lp' => false, 'callsign' => $znacka, 'locator' => 'JN99AJ',
                'qso_count' => 2, 'qso_points' => 7, 'multiplier' => 2, 'points' => 14,
                'name' => 'Test', 'email' => 't@t.cz', 'phone' => '', 'note' => '',
                'soapbox' => '', 'ip' => '', 'edi_head_id' => $h->id,
                'rank' => 1, 'approved' => true, 'session_id' => '',
            ]);
        }

        return $head;
    }
}
