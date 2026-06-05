<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use ZipArchive;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function kolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => '2026-03-15',
            'datum_uzaverky' => '2026-04-01',
            'nazev' => '1. kolo 2026',
            'poznamka' => '',
        ]);
    }

    /** Vytvoří ZIP soubor s danými položkami a vrátí ho jako UploadedFile. */
    private function makeZip(array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test_import_').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return new UploadedFile($path, 'import.zip', 'application/zip', null, true);
    }

    private function sampleEdi(string $pcall = 'OK2KJT'): string
    {
        return "[REG1TEST;1]\nTName=Test\nTDate=20260315;20260315\nPCall={$pcall}\nPWWLo=JN99AJ\nPSect=MULTI\nPBand=144 MHz\nRName=Test\nRPhon=\nRHBBS=\nSPowe=100\n[QSORecords;1]\n260315;0830;OK1AB;1;59;001;59;001;;JN89AA;3;;;;\n[END;]\n";
    }

    // ------------------------------------------------------------------
    // index

    public function test_index_renders_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('importy.index'))
            ->assertOk();
    }

    public function test_index_requires_admin(): void
    {
        $this->get(route('importy.index'))
            ->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // store – úspěšný import

    public function test_store_imports_single_edi_file_from_zip(): void
    {
        $this->kolo();

        $zip = $this->makeZip(['ok2kjt.edi' => $this->sampleEdi('OK2KJT')]);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip])
            ->assertRedirect(route('importy.index'))
            ->assertSessionHas('import_results');

        $results = session('import_results');
        $this->assertSame(1, $results['total']);
        $this->assertSame(1, $results['imported']);
        $this->assertSame(0, $results['errors']);

        $this->assertDatabaseHas('vkvpa_data', ['znacka' => 'OK2KJT', 'EDI' => true]);
    }

    public function test_store_imports_multiple_edi_files_from_zip(): void
    {
        $this->kolo();

        $zip = $this->makeZip([
            'ok2kjt.edi' => $this->sampleEdi('OK2KJT'),
            'ok1ab.edi' => $this->sampleEdi('OK1AB'),
        ]);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip])
            ->assertRedirect(route('importy.index'));

        $results = session('import_results');
        $this->assertSame(2, $results['total']);
        $this->assertSame(2, $results['imported']);
        $this->assertSame(2, VkvpaData::count());
    }

    // ------------------------------------------------------------------
    // store – skip (deník již existuje)

    public function test_store_skips_duplicate_edi_for_same_kolo(): void
    {
        $kolo = $this->kolo();
        VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => 0, 'znacka' => 'OK2KJT',
            'locator' => 'JN99AJ', 'pocet' => 1, 'nasobice' => 1, 'body' => 1,
            'bodu_za_qso' => 1, 'schvaleno' => false, 'EDI' => true,
        ]);

        $zip = $this->makeZip(['ok2kjt.edi' => $this->sampleEdi('OK2KJT')]);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip]);

        $results = session('import_results');
        $this->assertSame(1, $results['skipped']);
        $this->assertSame(0, $results['imported']);
        $this->assertSame(1, VkvpaData::count(), 'Nesmí vzniknout duplicitní záznam');
    }

    // ------------------------------------------------------------------
    // store – chybové stavy

    public function test_store_reports_error_for_invalid_edi_content(): void
    {
        $this->kolo();

        $zip = $this->makeZip(['bad.edi' => 'Tohle není platný EDI soubor']);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip]);

        $results = session('import_results');
        $this->assertSame(1, $results['errors']);
        $this->assertSame(0, $results['imported']);
    }

    public function test_store_reports_error_for_empty_edi_file(): void
    {
        $zip = $this->makeZip(['empty.edi' => '   ']);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip]);

        $results = session('import_results');
        $this->assertSame(1, $results['errors']);
    }

    public function test_store_ignores_non_edi_files_in_zip(): void
    {
        $this->kolo();

        $zip = $this->makeZip([
            'ok2kjt.edi' => $this->sampleEdi('OK2KJT'),
            'readme.txt' => "Ignoruj mě",
            'photo.jpg' => 'fake-image-data',
        ]);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip]);

        // readme.txt je .txt → může se zpracovat (chyba parsování), photo.jpg se ignoruje.
        // Klíčové: celkový počet nezahrnuje .jpg soubor.
        $results = session('import_results');
        $this->assertArrayHasKey('total', $results);
        $total = $results['total'];
        // .jpg je přeskočen, .edi a .txt se pokusily načíst (1 ok + 1 error).
        $this->assertSame(2, $total);
    }

    // ------------------------------------------------------------------
    // store – limit 200 souborů

    public function test_store_respects_200_file_limit(): void
    {
        $this->kolo();

        $files = [];
        for ($i = 1; $i <= 210; $i++) {
            $files["ok{$i}.edi"] = $this->sampleEdi("OK{$i}TEST");
        }

        $zip = $this->makeZip($files);

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $zip]);

        $results = session('import_results');
        $this->assertLessThanOrEqual(200, $results['total'], 'Limit 200 souborů musí být dodržen');
    }

    // ------------------------------------------------------------------
    // store – validace vstupu

    public function test_store_rejects_non_zip_file(): void
    {
        $file = UploadedFile::fake()->create('deník.edi', 10, 'text/plain');

        $this->actingAs($this->admin())
            ->post(route('importy.store'), ['zip' => $file])
            ->assertSessionHasErrors('zip');
    }

    public function test_store_requires_zip_file(): void
    {
        $this->actingAs($this->admin())
            ->post(route('importy.store'), [])
            ->assertSessionHasErrors('zip');
    }

    public function test_store_requires_admin(): void
    {
        $zip = $this->makeZip(['ok2kjt.edi' => $this->sampleEdi()]);

        $this->post(route('importy.store'), ['zip' => $zip])
            ->assertRedirect(route('login'));
    }
}
