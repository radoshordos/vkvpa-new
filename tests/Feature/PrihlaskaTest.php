<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Prihlaska;
use App\Models\Edihead;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
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
    private function koloProDatum(string $datum): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => $datum.' 08:00:00',
            'datum_uzaverky' => now()->addDay(),
            'nazev' => 'Kolo '.$datum,
            'poznamka' => '',
        ]);
    }

    /** @return array{VkvpaKola, VkvpaKategorie} */
    private function prepare(): array
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Testovací kolo',
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        return [$kolo, $kat];
    }

    // ── EDI: validace nahrání ────────────────────────────────────────────────

    public function test_invalid_extension_is_rejected(): void
    {
        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file('[REG1TEST;1]', 'denik.pdf'))
            ->assertHasErrors('upload');

        $this->assertSame(0, Edihead::count());
    }

    public function test_corrupt_edi_content_stays_in_choose_with_error(): void
    {
        $component = Livewire::test(Prihlaska::class)
            ->set('upload', $this->file('toto neni edi soubor'))
            ->assertSet('mode', 'choose');

        $this->assertNotSame('', $component->get('errorMessage'));
        $this->assertSame(0, Edihead::count());
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

        $this->assertSame(0, Edihead::count());
    }

    public function test_edi_with_tdate_not_matching_qsos_is_rejected(): void
    {
        $this->koloProDatum('2026-04-19');
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $edi = str_replace('TDate=20260315;20260315', 'TDate=20260419;20260419', $edi);

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'choose');

        $this->assertSame(0, Edihead::count());
    }

    public function test_edi_rejected_when_no_round_exists(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'choose');

        $this->assertSame(0, Edihead::count());
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

        $this->assertSame(1, Edihead::count());
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
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        $this->assertSame(1, Edihead::count());
    }

    public function test_edi_review_shows_per_qso_breakdown_and_files(): void
    {
        $this->koloProDatum('2026-03-15');
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        Livewire::test(Prihlaska::class)
            ->set('upload', $this->file($edi))
            ->assertSet('mode', 'edi-review')
            ->assertSee('Rozpad spojení')   // sbalitelný rozpad QSO
            ->assertSee('EDIR')             // odkaz na redukovaný soubor
            ->assertSee('PWWLo');           // obsah původního EDI je vidět
    }

    // ── Ruční podání ─────────────────────────────────────────────────────────

    public function test_manual_submission_is_stored_pending_and_redirects(): void
    {
        [$kolo, $kat] = $this->prepare();

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('kolo', $kolo->id)
            ->set('kategorie', $kat->id)
            ->set('znacka', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('email', 'test@example.com')
            ->set('pocet', 10)
            ->set('nasobice', 5)
            ->set('body', 50)
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        $row = VkvpaData::firstOrFail();
        $this->assertSame('OK2KJT', $row->znacka);  // uppercased
        $this->assertSame('JN99AJ', $row->locator);
        $this->assertNull($row->edihead_id);
        $this->assertFalse((bool) $row->schvaleno); // veřejnost čeká na převzetí
    }

    public function test_manual_admin_submission_is_approved(): void
    {
        [$kolo, $kat] = $this->prepare();
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('kolo', $kolo->id)
            ->set('kategorie', $kat->id)
            ->set('znacka', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('email', 'test@example.com')
            ->set('pocet', 10)
            ->set('nasobice', 5)
            ->set('body', 50)
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        $this->assertTrue((bool) VkvpaData::firstOrFail()->schvaleno);
    }

    public function test_manual_missing_email_is_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('kolo', $kolo->id)
            ->set('kategorie', $kat->id)
            ->set('znacka', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->call('odeslat')
            ->assertHasErrors('email');

        $this->assertSame(0, VkvpaData::count());
    }

    public function test_manual_duplicate_is_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK2KJT',
            'locator' => 'JN99AJ', 'mail' => 'x@y.cz', 'pocet' => 1, 'nasobice' => 1, 'body' => 1,
            'schvaleno' => true,
        ]);

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('kolo', $kolo->id)
            ->set('kategorie', $kat->id)
            ->set('znacka', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('email', 'test@example.com')
            ->call('odeslat')
            ->assertHasErrors('znacka');

        $this->assertSame(1, VkvpaData::count());
    }

    public function test_manual_rejected_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        $kolo->update(['datum_uzaverky' => now()->subDay()]); // okno zavřené

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('kolo', $kolo->id)
            ->set('kategorie', $kat->id)
            ->set('znacka', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('email', 'test@example.com')
            ->call('odeslat')
            ->assertHasErrors('kolo');

        $this->assertSame(0, VkvpaData::count());
    }

    public function test_manual_admin_allowed_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        $kolo->update(['datum_uzaverky' => now()->subDay()]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin);

        Livewire::test(Prihlaska::class)
            ->call('rucne')
            ->set('kolo', $kolo->id)
            ->set('kategorie', $kat->id)
            ->set('znacka', 'ok2kjt')
            ->set('locator', 'jn99aj')
            ->set('email', 'test@example.com')
            ->call('odeslat')
            ->assertRedirect(route('pribezne_vysledky'));

        $this->assertSame(1, VkvpaData::count());
    }
}
