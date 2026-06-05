<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Integrační testy celého EDI pipeline:
 *   HTTP upload → EdiParser → EdiImportService → ScoringService → VkvpaData
 *
 * Ověřujeme, že každý krok předá správná data dalšímu – zvlášť scoring hodnoty,
 * které existující unit testy testují izolovaně, ale ne jako celek přes HTTP.
 */
class EdiPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $sampleEdi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleEdi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
    }

    /** @return TestResponse<Response> */
    private function upload(): TestResponse
    {
        $file = UploadedFile::fake()->createWithContent('sample.edi', $this->sampleEdi);

        return $this->post('/edi', ['upload' => $file]);
    }

    private function koloProBrezen2026(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-04-01',
            'nazev' => '1. kolo 2026',
            'aktivni' => true,
            'poznamka' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // Databázový stav po uploadu

    public function test_upload_creates_edihead_edilines_and_vkvpa_data_rows(): void
    {
        $this->upload();

        $this->assertSame(1, Edihead::count(), 'Musí vzniknout 1 edihead');
        $this->assertSame(2, Ediline::count(), 'sample.edi má 2 QSO řádky');
        $this->assertSame(1, VkvpaData::count(), 'Musí vzniknout 1 rezervovaný řádek');
    }

    public function test_upload_sets_correct_scoring_values_in_vkvpa_data(): void
    {
        $this->upload();

        $row = VkvpaData::firstOrFail();

        // sample.edi: home JN99, QSO do JN99BP (vlastní, 2 b.) a JN89PV (soused, 3 b.)
        // pocet=2, boduZaQso=5, nasobice=2 (JN89+JN99), body=10
        $this->assertSame(2, $row->pocet, 'pocet QSO');
        $this->assertSame(5, $row->bodu_za_qso, 'body za spojení');
        $this->assertSame(2, $row->nasobice, 'násobič');
        $this->assertSame(10, $row->body, 'celkové body');
    }

    public function test_upload_scoring_matches_direct_service_calculation(): void
    {
        $this->upload();

        $row = VkvpaData::firstOrFail();
        $head = Edihead::findOrFail((int) $row->EDI_ID);

        $direct = app(ScoringService::class)->scoreEdi($head);

        $this->assertSame($direct->pocet, $row->pocet);
        $this->assertSame($direct->boduZaQso, $row->bodu_za_qso);
        $this->assertSame($direct->nasobice, $row->nasobice);
        $this->assertSame($direct->body, $row->body);
    }

    public function test_upload_stores_edi_flag_and_edi_id(): void
    {
        $this->upload();

        $row = VkvpaData::firstOrFail();
        $this->assertTrue($row->EDI, 'EDI příznak musí být true');
        $this->assertGreaterThan(0, $row->EDI_ID, 'EDI_ID musí odkazovat na edihead');
        $this->assertNotNull(Edihead::find($row->EDI_ID), 'Edihead musí existovat');
    }

    public function test_upload_creates_reserved_row_with_schvaleno_false(): void
    {
        $this->upload();

        $row = VkvpaData::firstOrFail();
        $this->assertFalse((bool) $row->schvaleno, 'Rezervovaný řádek čeká na převzetí');
    }

    // ------------------------------------------------------------------
    // Viditelnost ve výsledcích

    public function test_entry_hidden_before_approval_visible_after(): void
    {
        $kolo = $this->koloProBrezen2026();
        DB::table('vkvpa_kategorie')->insert(['id' => 2, 'nazev' => '144 MHz MO', 'popis' => '', 'zkratka' => '144 MO', 'dxid' => 0]);

        $this->upload();

        $row = VkvpaData::firstOrFail();

        // Nezveřejněný záznam není vidět pro anonymního uživatele.
        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertDontSee('OK2KJT');

        // Admin ho vidí (meruňkové pozadí).
        $admin = User::create(['name' => 'A', 'password' => Hash::make('x'), 'is_admin' => true]);
        $this->actingAs($admin)
            ->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('OK2KJT');

        // Po převzetí je vidět i anonymně.
        $this->actingAs($admin)->patch(route('zaznam.update', ['zaznam' => $row->id]));

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('OK2KJT');
    }

    // ------------------------------------------------------------------
    // Celý tok: upload → formulář → finalizace

    public function test_full_flow_upload_then_form_submission_finalizes_record(): void
    {
        $this->koloProBrezen2026();
        DB::table('vkvpa_kategorie')->insert(['id' => 2, 'nazev' => '144 MHz MO', 'popis' => '', 'zkratka' => '144 MO', 'dxid' => 0]);

        // Krok 1: upload → rezervovaný řádek + session.
        $this->upload()->assertRedirect(route('hlaseni.index', ['import' => 'success']));
        $row = VkvpaData::firstOrFail();
        // session() helper čte ze session posledního requestu.
        $ownedId = session('owned_data_id');

        // Krok 2: uživatel vyplní formulář a odešle → záznam finalizován.
        $this->withSession(['owned_data_id' => $ownedId])
            ->post('/hlaseni', [
                'id_zaznamu' => $row->id,
                'kolo' => $row->id_kola,
                'kategorie' => $row->id_kategorie,
                'znacka' => 'OK2KJT',
                'locator' => 'JN99AJ',
                'email' => 'test@example.com',
                'pocet' => $row->pocet,
                'bodu_za_qso' => $row->bodu_za_qso,
                'nasobice' => $row->nasobice,
                'body' => $row->body,
                'EDIID' => $row->EDI_ID,
            ])
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $row->id_kola]));

        $row->refresh();
        $this->assertSame('OK2KJT', $row->znacka);
        $this->assertTrue((bool) $row->schvaleno, 'Po odeslání formuláře musí být schvaleno=true');
        $this->assertSame(1, VkvpaData::count(), 'Nesmí vzniknout duplicitní záznam');
    }
}
