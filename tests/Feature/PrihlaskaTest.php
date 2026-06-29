<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Prihlaska;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Livewire komponent Prihlaska – jednotné podání hlášení (EDI náhled i ruční),
 * kde se do DB zapíše až po stisku „Odeslat".
 */
class PrihlaskaTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $edi, string $name = 'denik.edi'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $edi);
    }

    /** Založí kolo s daným dnem konání (otevřené okno příjmu). */
    private function koloProDatum(string $datum): EdiRound
    {
        return EdiRound::create([
            'starts_at' => $datum.' 08:00:00',
            'closes_at' => now()->addDay(),
            'name' => 'Kolo '.$datum,
            'note' => '',
        ]);
    }

    /** @return array{EdiRound, EdiCategory} */
    private function prepare(): array
    {
        $kolo = EdiRound::create([
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'name' => 'Testovací kolo',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);

        return [$kolo, $kat];
    }

    // ── EDI: validace nahrání ────────────────────────────────────────────────

    public function test_invalid_extension_is_rejected(): void
    {
        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file('[REG1TEST;1]', 'denik.pdf'))
            ->assertHasErrors('upload');

        $this->assertSame(0, EdiHead::count());
    }

    public function test_corrupt_edi_content_stays_in_choose_with_error(): void
    {
        $component = Livewire::test(Prihlaska::class)
            ->set('upload', $this->file('toto neni edi soubor'))
            ->assertSet('mode', 'choose');

        $this->assertNotSame('', $component->get('errorMessage'));
        $this->assertSame(0, EdiHead::count());
    }

    // ── EDI: business validace (náhled nic neuloží) ──────────────────────────

    public function test_edi_with_unknown_band_is_rejected(): void
    {
        $this->koloProDatum('2026-03-15');
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $edi = str_replace('PBand=144 MHz', 'PBand=14 MHz', $edi);

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'choose');

        $this->assertSame(0, EdiHead::count());
    }

    public function test_edi_with_tdate_not_matching_qsos_is_rejected(): void
    {
        $this->koloProDatum('2026-04-19');
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $edi = str_replace('TDate=20260315;20260315', 'TDate=20260419;20260419', $edi);

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'choose');

        $this->assertSame(0, EdiHead::count());
    }

    public function test_edi_rejected_when_no_round_exists(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'choose');

        $this->assertSame(0, EdiHead::count());
    }

    public function test_duplicate_edi_is_rejected(): void
    {
        $this->koloProDatum('2026-03-15');
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        // První deník projde.
        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->set('email', 'a@example.com')
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        // Druhý (stejná značka + kolo) je odmítnut už v náhledu.
        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'choose');

        $this->assertSame(1, EdiHead::count());
    }

    public function test_edi_tdate_range_accepted_when_one_day_is_third_sunday(): void
    {
        $this->koloProDatum('2026-01-18');
        $edi = implode("\n", [
            '[REG1TEST;1]',
            'TName=VKV PA 2026/01',
            'TDate=20260117;20260118',
            'PCall=OK1RNG',
            'PWWLo=JN99AJ',
            'PSect=MULTI',
            'PBand=144 MHz',
            'RName=Test',
            'RHBBS=ok1rng@example.com',
            'SPowe=100',
            '[QSORecords;1]',
            '260117;0830;OK1AB;1;59;001;59;001;;JN89AA;3;;;;',
            '[END;]',
        ])."\n";

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->set('email', 'ok1rng@example.com')
            ->set('phone', '+420 777 123 456')
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        $this->assertSame(1, EdiHead::count());
    }

    public function test_edi_review_shows_per_qso_breakdown_and_files(): void
    {
        $this->koloProDatum('2026-03-15');
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'edi-review')
            ->assertSee('Analýza spojení')  // sbalitelný rozpad QSO
            ->assertSee('EDIR')             // odkaz na redukovaný soubor
            ->assertSee('PWWLo');           // obsah původního EDI je vidět
    }

    public function test_edi_review_colors_out_of_window_lines_dropped_by_edir(): void
    {
        $this->koloProDatum('2026-03-15');
        // QSO ve 12:30 je mimo okno → EDIR ho zahodí → v EDI náhledu zvýrazněno.
        $edi = "[REG1TEST;1]\nTName=Provozni aktiv\nTDate=20260315;20260315\n"
            ."PCall=OK2KJT\nPWWLo=JN99AJ\nPSect=MULTI\nPBand=144 MHz\n"
            ."RHBBS=ok2kjt@example.com\nSPowe=100\n[QSORecords;2]\n"
            ."260315;0830;OK1IN;1;59;001;59;001;;JN89PV;3;;;;\n"
            ."260315;1230;OK1OUT;1;59;002;59;002;;JN79VS;3;;;;\n[END;]\n";

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'edi-review')
            ->assertSee('var(--danger-soft)', false); // zahozený řádek je obarven
    }

    // ── Ruční podání ─────────────────────────────────────────────────────────

    public function test_manual_submission_is_stored_pending_and_redirects(): void
    {
        [$kolo, $kat] = $this->prepare();

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('round', $kolo->id)
            ->set('category', $kat->id)
            ->set('callsign', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('name', 'Jan Novák')
            ->set('email', 'test@example.com')
            ->set('phone', '+420 777 123 456')
            ->set('qsoCount', 10)
            ->set('multiplier', 5)
            ->set('points', 50)
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        $row = EdiEntry::firstOrFail();
        $this->assertSame('OK2KJT', $row->callsign);  // uppercased
        $this->assertSame('JN99AJ', $row->locator);
        $this->assertNull($row->edi_head_id);
        $this->assertFalse((bool) $row->approved); // veřejnost čeká na převzetí
    }

    public function test_manual_admin_submission_is_approved(): void
    {
        [$kolo, $kat] = $this->prepare();
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('round', $kolo->id)
            ->set('category', $kat->id)
            ->set('callsign', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('name', 'Jan Novák')
            ->set('email', 'test@example.com')
            ->set('phone', '+420 777 123 456')
            ->set('qsoCount', 10)
            ->set('multiplier', 5)
            ->set('points', 50)
            ->call('odeslat')
            // Admin se přesměruje rovnou na kolo svého hlášení (smí listovat v kolech).
            ->assertRedirect(route('pribezne_vysledky', ['kolo' => $kolo->id]));

        $this->assertTrue((bool) EdiEntry::firstOrFail()->approved);
    }

    public function test_manual_submission_without_email_is_accepted(): void
    {
        [$kolo, $kat] = $this->prepare();

        $component = Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('round', $kolo->id)
            ->set('category', $kat->id)
            ->set('callsign', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('name', 'Jan Novák')
            ->set('phone', '+420 777 123 456')
            ->call('odeslat');

        $component->assertHasNoErrors('email');
        $component->assertRedirect(route('pribezne_vysledky'));

        $this->assertSame(1, EdiEntry::count());
    }

    public function test_manual_duplicate_is_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK2KJT',
            'locator' => 'JN99AJ', 'email' => 'x@y.cz', 'qso_count' => 1, 'multiplier' => 1, 'points' => 1,
            'approved' => true,
        ]);

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('round', $kolo->id)
            ->set('category', $kat->id)
            ->set('callsign', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('name', 'Jan Novák')
            ->set('email', 'test@example.com')
            ->set('phone', '+420 777 123 456')
            ->call('odeslat')
            ->assertHasErrors('callsign');

        $this->assertSame(1, EdiEntry::count());
    }

    public function test_manual_rejected_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        $kolo->update(['closes_at' => now()->subDay()]); // okno zavřené

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('round', $kolo->id)
            ->set('category', $kat->id)
            ->set('callsign', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('name', 'Jan Novák')
            ->set('email', 'test@example.com')
            ->set('phone', '+420 777 123 456')
            ->call('odeslat')
            ->assertHasErrors('round');

        $this->assertSame(0, EdiEntry::count());
    }

    public function test_manual_admin_allowed_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        $kolo->update(['closes_at' => now()->subDay()]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('round', $kolo->id)
            ->set('category', $kat->id)
            ->set('callsign', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('name', 'Jan Novák')
            ->set('email', 'test@example.com')
            ->set('phone', '+420 777 123 456')
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
    }
}
