<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HlaseniTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{VkvpaKola, VkvpaKategorie} */
    private function prepare(): array
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Testovací kolo',
            'aktivni' => true,
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        return [$kolo, $kat];
    }

    /** @return array<string, mixed> */
    private function payload(int $kolo, int $kat): array
    {
        return [
            'kolo' => $kolo,
            'kategorie' => $kat,
            'znacka' => 'ok2kjt',
            'locator' => 'jn99aj',
            'email' => 'test@example.com',
            'pocet' => 10,
            'nasobice' => 5,
            'body' => 50,
        ];
    }

    public function test_valid_report_is_stored_and_approved(): void
    {
        [$kolo, $kat] = $this->prepare();

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertSessionHas('announcement');

        $row = VkvpaData::first();
        $this->assertNotNull($row);
        $this->assertSame('OK2KJT', $row->znacka);   // uppercased
        $this->assertSame('JN99AJ', $row->locator);
        $this->assertSame('test@example.com', $row->mail);
        $this->assertTrue((bool) $row->schvaleno);   // auto-approve
    }

    public function test_missing_required_fields_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['email']);

        $this->post('/hlaseni', $payload)->assertSessionHasErrors('email');
        $this->assertSame(0, VkvpaData::count());
    }

    public function test_editing_existing_requires_admin(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1OLD',
            'locator' => 'JO70AA', 'mail' => 'x@y.cz', 'pocet' => 1, 'nasobice' => 1, 'body' => 1,
            'schvaleno' => true,
        ]);

        $payload = $this->payload($kolo->id, $kat->id);
        $payload['id_zaznamu'] = $existing->id;

        // Anonym nesmí editovat existující záznam.
        $this->post('/hlaseni', $payload)->assertForbidden();
    }

    public function test_edi_upload_creates_reserved_row_and_session(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $file = UploadedFile::fake()->createWithContent('02OK2KJT.edi', $edi);

        $resp = $this->post('/edi', ['upload' => $file])
            ->assertRedirect(route('edit_hlaseni', ['import' => 'success']));

        $this->assertSame(1, Edihead::count());

        $row = VkvpaData::first();
        $this->assertNotNull($row);
        $this->assertSame('OK2KJT', $row->znacka);
        $this->assertFalse((bool) $row->schvaleno);          // rezervovaný řádek = Čeká
        // Kategorie určena z hlavičky: PBand 144 MHz + PSect MULTI + OK → „144 MHz multi op" (id 2).
        $this->assertSame(2, $row->id_kategorie);
        $resp->assertSessionHas('owned_data_id', $row->id);  // vlastní řádek v session
    }

    public function test_edi_upload_with_unknown_band_is_rejected(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        // Změníme pásmo na nerozpoznané (KV 14 MHz) → deník se má odmítnout.
        $edi = str_replace('PBand=144 MHz', 'PBand=14 MHz', $edi);

        $this->post('/edi', ['upload' => UploadedFile::fake()->createWithContent('x.edi', $edi)])
            ->assertSessionHasErrors('upload');

        $this->assertSame(0, Edihead::count()); // nic se neimportovalo
    }

    public function test_edi_upload_with_tdate_not_matching_qsos_is_rejected(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        // Datum v hlavičce posuneme na jiný den, než mají QSO řádky (260315) → bez opravy
        // by se skóre spočítalo z 0 QSO; deník proto odmítneme.
        $edi = str_replace('TDate=20260315;20260315', 'TDate=20260418;20260418', $edi);

        $this->post('/edi', ['upload' => UploadedFile::fake()->createWithContent('x.edi', $edi)])
            ->assertSessionHasErrors('upload');

        $this->assertSame(0, Edihead::count()); // nic se neimportovalo
    }

    public function test_duplicate_edi_upload_is_rejected(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        // První nahrání projde.
        $this->post('/edi', ['upload' => UploadedFile::fake()->createWithContent('a.edi', $edi)])
            ->assertRedirect(route('edit_hlaseni', ['import' => 'success']));

        // Druhé nahrání téhož deníku (stejná značka + kolo) → odmítnuto s hláškou.
        $this->post('/edi', ['upload' => UploadedFile::fake()->createWithContent('b.edi', $edi)])
            ->assertSessionHasErrors('upload');

        $this->assertSame(1, Edihead::count()); // druhý import se neuložil
    }

    public function test_submission_to_inactive_round_is_blocked(): void
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Neaktivní kolo',
            'aktivni' => false,
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => 'A', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertSessionHasErrors('kolo');
        $this->assertSame(0, VkvpaData::count());
    }

    public function test_admin_can_edit_any_record(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1OLD',
            'locator' => 'JO70AA', 'mail' => 'x@y.cz', 'pocet' => 1, 'nasobice' => 1, 'body' => 1,
            'schvaleno' => true,
        ]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $payload = $this->payload($kolo->id, $kat->id);
        $payload['id_zaznamu'] = $existing->id;

        $this->actingAs($admin)
            ->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame('OK2KJT', $existing->refresh()->znacka); // 'ok2kjt' → uppercase
    }

    public function test_session_owner_can_edit_their_own_record(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1OLD',
            'locator' => 'JO70AA', 'mail' => 'x@y.cz', 'pocet' => 1, 'nasobice' => 1, 'body' => 1,
            'schvaleno' => true,
        ]);

        $payload = $this->payload($kolo->id, $kat->id);
        $payload['id_zaznamu'] = $existing->id;

        $this->withSession(['owned_data_id' => $existing->id])
            ->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame('OK2KJT', $existing->refresh()->znacka);
    }

    public function test_admin_routes_require_login(): void
    {
        $this->get('/admin/kategorie')->assertRedirect(route('login'));
    }
}
