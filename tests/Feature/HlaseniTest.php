<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz', 'zkratka' => 'A', 'dxid' => 0]);

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
            'jmeno' => 'Jan Novák',
            'email' => 'test@example.com',
            'telefon' => '+420 777 123 456',
            'pocet' => 10,
            'nasobice' => 5,
            'body' => 50,
        ];
    }

    public function test_valid_report_is_stored_and_pending(): void
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
        // Hlášení od veřejnosti čeká na převzetí vyhodnocovatelem (není auto-approve).
        $this->assertFalse((bool) $row->schvaleno);
    }

    public function test_admin_report_is_stored_and_approved(): void
    {
        [$kolo, $kat] = $this->prepare();
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        // Administrátor smí záznam rovnou převzít (schvaleno=true).
        $this->assertTrue((bool) VkvpaData::firstOrFail()->schvaleno);
    }

    public function test_hlaseni_page_hides_forms_outside_upload_window(): void
    {
        [$kolo] = $this->prepare();
        $kolo->update(['datum_uzaverky' => now()->subDay()]);

        // Mimo upload okno se podávací komponent (drop-zóna EDI) nenabízí.
        $this->get('/hlaseni')
            ->assertOk()
            ->assertDontSee('id="edi-file"', false);
    }

    public function test_hlaseni_page_shows_forms_in_prijem_state(): void
    {
        [$kolo] = $this->prepare();
        // Den závodu proběhl, uzávěrka v budoucnu → stav Příjem.
        $kolo->update(['datum_uzaverky' => now()->addDay()]);

        // Ve stavu Příjem se vykreslí Livewire komponent s drop-zónou pro EDI.
        $this->get('/hlaseni')
            ->assertOk()
            ->assertSee('id="edi-file"', false);
    }

    public function test_manual_report_rejected_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        // Uzávěrka uplynula → stav Uzavřené, okno zavřené.
        $kolo->update(['datum_uzaverky' => now()->subDay()]);

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertSessionHasErrors('kolo');
        $this->assertSame(0, VkvpaData::count());
    }

    public function test_admin_can_store_report_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        $kolo->update(['datum_uzaverky' => now()->subDay()]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, VkvpaData::count());
    }

    public function test_missing_required_fields_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['jmeno'], $payload['telefon']);

        $this->post('/hlaseni', $payload)->assertSessionHasErrors(['jmeno', 'telefon']);
        $this->assertSame(0, VkvpaData::count());
    }

    public function test_manual_report_without_email_accepted(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['email']);

        $this->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, VkvpaData::count());
        $this->assertSame('', VkvpaData::firstOrFail()->mail);
    }

    public function test_invalid_phone_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        $payload['telefon'] = 'abc';

        $this->post('/hlaseni', $payload)->assertSessionHasErrors('telefon');
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

    public function test_duplicate_manual_submission_is_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect();

        $this->assertSame(1, VkvpaData::count());

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertSessionHasErrors('znacka');

        $this->assertSame(1, VkvpaData::count());
    }

    public function test_submission_to_upcoming_round_is_blocked(): void
    {
        // Závod ještě nezačal (stav Nadcházející) → hlášení se nepřijímají.
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->addDays(5),
            'datum_uzaverky' => now()->addDays(10),
            'nazev' => 'Nadcházející kolo',
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => 'A', 'zkratka' => 'A', 'dxid' => 0]);

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

    public function test_anonymous_cannot_view_foreign_record_pii_via_id(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1PII',
            'locator' => 'JO70AA', 'mail' => 'secret@example.com', 'telefon' => '+420123456789',
            'jmeno' => 'Tajné Jméno', 'pocet' => 1, 'nasobice' => 1, 'body' => 1, 'schvaleno' => true,
        ]);

        // Anonym přes ?id nesmí dostat PII cizího záznamu do prefillu formuláře.
        $resp = $this->get(route('hlaseni.index', ['id' => $existing->id]))->assertOk();
        $resp->assertDontSee('secret@example.com');
        $resp->assertDontSee('+420123456789');
        $resp->assertDontSee('Tajné Jméno');
    }

    public function test_admin_can_view_record_pii_via_id(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1PII',
            'locator' => 'JO70AA', 'mail' => 'secret@example.com', 'pocet' => 1,
            'nasobice' => 1, 'body' => 1, 'schvaleno' => true,
        ]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('hlaseni.index', ['id' => $existing->id]))
            ->assertOk()
            ->assertSee('secret@example.com');
    }
}
