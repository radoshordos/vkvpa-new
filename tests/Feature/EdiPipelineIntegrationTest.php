<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Prihlaska;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Models\EdiLine;
use App\Models\EdiRound;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Integrační testy celého EDI pipeline přes Livewire komponent Prihlaska:
 *   nahrání souboru (náhled, BEZ zápisu) → „Odeslat" → EdiParser → EdiImportService
 *   → ScoringService → EdiEntry.
 *
 * Ověřujeme, že náhled nic neuloží a že „Odeslat" předá správná data dál –
 * zvlášť scoring hodnoty.
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

    private function file(?string $edi = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('sample.edi', $edi ?? $this->sampleEdi);
    }

    /**
     * Náhled (nahrání souboru) – do DB se nic nezapíše.
     *
     * @return Testable<Prihlaska>
     */
    private function nahled(?string $edi = null): Testable
    {
        return Livewire::test(Prihlaska::class)->set('upload', $this->file($edi));
    }

    /**
     * Celý tok: náhled + „Odeslat" (uloží).
     *
     * @return Testable<Prihlaska>
     */
    private function odeslat(?string $edi = null): Testable
    {
        return $this->nahled($edi)->set('email', 'test@example.com')->call('odeslat');
    }

    private function koloProBrezen2026(): EdiRound
    {
        return EdiRound::create([
            'starts_at' => '2026-03-15 08:00:00',
            // Uzávěrka v budoucnu → upload okno (stav Příjem) je otevřené.
            'closes_at' => now()->addDay(),
            'name' => '1. kolo 2026',
            'note' => '',
        ]);
    }

    // ------------------------------------------------------------------
    // Náhled nic neukládá

    public function test_preview_does_not_write_to_database(): void
    {
        $this->koloProBrezen2026();

        $this->nahled()->assertSet('mode', 'edi-review');

        $this->assertSame(0, EdiHead::count(), 'Náhled nesmí nic importovat');
        $this->assertSame(0, EdiLine::count());
        $this->assertSame(0, EdiEntry::count());
    }

    // ------------------------------------------------------------------
    // Databázový stav po odeslání

    public function test_submit_creates_edihead_edilines_and_edi_entries_rows(): void
    {
        $this->koloProBrezen2026();
        $this->odeslat()->assertRedirect(route('pribezne_vysledky'));

        $this->assertSame(1, EdiHead::count(), 'Musí vzniknout 1 edihead');
        $this->assertSame(2, EdiLine::count(), 'sample.edi má 2 QSO řádky');
        $this->assertSame(1, EdiEntry::count(), 'Musí vzniknout 1 řádek');
    }

    // ------------------------------------------------------------------
    // Upload okno – deník lze odeslat jen u kola ve stavu Aktivní/Příjem

    public function test_upload_rejected_when_round_is_closed(): void
    {
        // Uzávěrka v minulosti → stav Uzavřené, okno zavřené.
        $this->koloProBrezen2026()->update(['closes_at' => now()->subDay()]);

        $component = $this->nahled()->assertSet('mode', 'choose');
        $this->assertNotSame('', $component->get('errorMessage'), 'Náhled má zobrazit chybu zavřeného okna');
        $this->assertSame(0, EdiEntry::count(), 'Mimo upload okno nesmí vzniknout záznam');
    }

    public function test_upload_rejected_when_round_is_evaluated(): void
    {
        $this->koloProBrezen2026()->update(['evaluated_at' => now()]);

        $this->nahled()->assertSet('mode', 'choose');
        $this->assertSame(0, EdiEntry::count(), 'Do vyhodnoceného kola nesmí vzniknout záznam');
    }

    public function test_submit_allowed_when_round_in_prijem_state(): void
    {
        // Den závodu proběhl, uzávěrka v budoucnu → stav Příjem.
        $this->koloProBrezen2026();

        $this->odeslat()->assertRedirect(route('pribezne_vysledky'));
        $this->assertSame(1, EdiEntry::count(), 'Ve stavu Příjem se deník přijme');
    }

    public function test_submit_sets_correct_scoring_values_in_edi_entries(): void
    {
        $this->koloProBrezen2026();
        $this->odeslat();

        $row = EdiEntry::firstOrFail();

        // sample.edi: home JN99, QSO do JN99BP (vlastní, 2 b.) a JN89PV (soused, 3 b.)
        // pocet=2, boduZaQso=5, multiplier=2 (JN89+JN99), body=10
        $this->assertSame(2, $row->qso_count, 'pocet QSO');
        $this->assertSame(5, $row->qso_points, 'body za spojení');
        $this->assertSame(2, $row->multiplier, 'násobič');
        $this->assertSame(10, $row->points, 'celkové body');
    }

    public function test_submit_scoring_matches_direct_service_calculation(): void
    {
        $this->koloProBrezen2026();
        $this->odeslat();

        $row = EdiEntry::firstOrFail();
        $head = EdiHead::findOrFail((int) $row->edi_head_id);

        $direct = app(ScoringService::class)->scoreEdi($head);

        $this->assertSame($direct->qsoCount, $row->qso_count);
        $this->assertSame($direct->qsoPoints, $row->qso_points);
        $this->assertSame($direct->multiplier, $row->multiplier);
        $this->assertSame($direct->points, $row->points);
    }

    public function test_submit_stores_edihead_id(): void
    {
        $this->koloProBrezen2026();
        $this->odeslat();

        $row = EdiEntry::firstOrFail();
        $this->assertNotNull($row->edi_head_id, 'edihead_id musí odkazovat na edihead');
        $this->assertNotNull(EdiHead::find($row->edi_head_id), 'EdiHead musí existovat');
    }

    public function test_submit_creates_pending_row_with_schvaleno_false(): void
    {
        $this->koloProBrezen2026();
        $this->odeslat();

        $row = EdiEntry::firstOrFail();
        $this->assertFalse((bool) $row->approved, 'Veřejné hlášení čeká na převzetí');
    }

    public function test_submit_uses_edited_contact_email(): void
    {
        $this->koloProBrezen2026();

        // Závodník v náhledu přepíše kontaktní e-mail – uloží se upravená hodnota.
        $this->nahled()->set('email', 'novy@example.com')->call('odeslat');

        $this->assertSame('novy@example.com', EdiEntry::firstOrFail()->email);
    }

    // ------------------------------------------------------------------
    // Viditelnost ve výsledcích

    public function test_entry_hidden_before_approval_visible_after(): void
    {
        $kolo = $this->koloProBrezen2026();
        // kategorie id 2 (144 MHz multi op) už je v edi_categories naseedovaná (TestCase)

        $this->odeslat();

        $row = EdiEntry::firstOrFail();

        // Nezveřejněný záznam není vidět pro anonymního uživatele.
        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertDontSee('OK2KJT');

        // Admin ho vidí (meruňkové pozadí).
        $admin = $this->makeUser('A', isAdmin: true);
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
    // Kontrola kvality deníku v náhledu

    public function test_preview_shows_quality_warnings_for_duplicate_qso(): void
    {
        $this->koloProBrezen2026();

        // Deník s duplicitním spojením (OK2IMH 2×) a QSO mimo okno (12:30).
        $edi = "[REG1TEST;1]\nTName=Provozni aktiv\nTDate=20260315;20260315\n"
            ."PCall=OK2KJT\nPWWLo=JN99AJ\nPSect=MULTI\nPBand=144 MHz\n"
            ."RHBBS=ok2ulq@seznam.cz\nSPowe=800W\n[QSORecords;3]\n"
            ."260315;0800;OK2IMH;1;59;001;59;001;;JN99BP;2;;;;\n"
            ."260315;0805;OK2IMH;1;59;002;59;002;;JN99BP;2;;;;\n"
            ."260315;1230;OK1XYZ;1;59;003;59;003;;JN79VS;3;;;;\n[END;]\n";

        $component = $this->nahled($edi)->assertSet('mode', 'edi-review');

        /** @var list<string> $warnings */
        $warnings = $component->get('warnings');
        $joined = implode(' ', $warnings);
        $this->assertStringContainsString('OK2IMH (2×)', $joined);
        $this->assertStringContainsString('mimo závodní okno', $joined);
    }

    public function test_temporary_uploaded_file_is_parseable(): void
    {
        // Pojistka: Livewire dočasný upload musí mít čitelný obsah (parseUpload
        // čte přes getRealPath) – jinak by celý tok tiše selhal.
        $this->koloProBrezen2026();
        $component = $this->nahled();

        $this->assertInstanceOf(TemporaryUploadedFile::class, $component->get('upload'));
        $component->assertSet('pcall', 'OK2KJT');
    }
}
