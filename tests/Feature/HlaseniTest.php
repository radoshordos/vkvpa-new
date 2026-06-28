<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HlaseniTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{EdiRound, EdiCategory} */
    private function prepare(): array
    {
        $kolo = EdiRound::create([
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'name' => 'Testovací kolo',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);

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
            'multiplier' => 5,
            'body' => 50,
        ];
    }

    public function test_valid_report_is_stored_and_pending(): void
    {
        [$kolo, $kat] = $this->prepare();

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertSessionHas('announcement');

        $row = EdiEntry::first();
        $this->assertNotNull($row);
        $this->assertSame('OK2KJT', $row->callsign);   // uppercased
        $this->assertSame('JN99AJ', $row->locator);
        $this->assertSame('test@example.com', $row->email);
        // Hlášení od veřejnosti čeká na převzetí vyhodnocovatelem (není auto-approve).
        $this->assertFalse((bool) $row->approved);
    }

    public function test_admin_report_is_stored_and_approved(): void
    {
        [$kolo, $kat] = $this->prepare();
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        // Administrátor smí záznam rovnou převzít (approved=true).
        $this->assertTrue((bool) EdiEntry::firstOrFail()->approved);
    }

    public function test_hlaseni_page_hides_forms_outside_upload_window(): void
    {
        [$kolo] = $this->prepare();
        $kolo->update(['closes_at' => now()->subDay()]);

        // Mimo upload okno se podávací komponent (drop-zóna EDI) nenabízí.
        $this->get('/hlaseni')
            ->assertOk()
            ->assertDontSee('id="edi-file"', false);
    }

    public function test_hlaseni_page_shows_forms_in_prijem_state(): void
    {
        [$kolo] = $this->prepare();
        // Den závodu proběhl, uzávěrka v budoucnu → stav Příjem.
        $kolo->update(['closes_at' => now()->addDay()]);

        // Ve stavu Příjem se vykreslí Livewire komponent s drop-zónou pro EDI.
        $this->get('/hlaseni')
            ->assertOk()
            ->assertSee('id="edi-file"', false);
    }

    public function test_manual_report_rejected_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        // Uzávěrka uplynula → stav Uzavřené, okno zavřené.
        $kolo->update(['closes_at' => now()->subDay()]);

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertSessionHasErrors('kolo');
        $this->assertSame(0, EdiEntry::count());
    }

    public function test_admin_can_store_report_outside_upload_window(): void
    {
        [$kolo, $kat] = $this->prepare();
        $kolo->update(['closes_at' => now()->subDay()]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
    }

    public function test_missing_required_fields_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['jmeno']);

        $this->post('/hlaseni', $payload)->assertSessionHasErrors('jmeno');
        $this->assertSame(0, EdiEntry::count());
    }

    public function test_manual_report_without_email_accepted(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['email']);

        $this->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
        $this->assertSame('', EdiEntry::firstOrFail()->email);
    }

    public function test_manual_report_without_phone_accepted(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['telefon']);

        $this->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
        $this->assertSame('', EdiEntry::firstOrFail()->phone);
    }

    public function test_manual_report_without_any_contact_is_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        unset($payload['email'], $payload['telefon']);

        // Pravidlo „alespoň jeden kontakt" platí jednotně i pro ruční podání.
        $this->post('/hlaseni', $payload)->assertSessionHasErrors('telefon');
        $this->assertSame(0, EdiEntry::count());
    }

    public function test_invalid_phone_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        $payload['telefon'] = 'abc';

        $this->post('/hlaseni', $payload)->assertSessionHasErrors('telefon');
        $this->assertSame(0, EdiEntry::count());
    }

    /** Vytvoří EDI hlavičku navázanou na kolo (pro testy podání s deníkem). */
    private function ediHead(int $kolo): Edihead
    {
        return Edihead::create([
            'round_id' => $kolo, 't_date' => '20260315', 'p_call' => 'OK2KJT',
            'p_wwlo' => 'JN99', 'p_band' => '144 MHz', 'r_name' => 'Jan',
            'r_emai' => 'a@a.cz', 's_powe' => 100,
        ]);
    }

    public function test_edi_report_with_only_phone_is_accepted(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        $payload['edihead_id'] = $this->ediHead($kolo->id)->id;
        unset($payload['email']);

        $this->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
        $this->assertSame('', EdiEntry::firstOrFail()->email);
    }

    public function test_edi_report_with_only_email_is_accepted(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        $payload['edihead_id'] = $this->ediHead($kolo->id)->id;
        unset($payload['telefon']);

        $this->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
        $this->assertSame('', EdiEntry::firstOrFail()->phone);
    }

    public function test_edi_report_without_any_contact_is_rejected(): void
    {
        [$kolo, $kat] = $this->prepare();
        $payload = $this->payload($kolo->id, $kat->id);
        $payload['edihead_id'] = $this->ediHead($kolo->id)->id;
        unset($payload['email'], $payload['telefon']);

        $this->post('/hlaseni', $payload)->assertSessionHasErrors('telefon');
        $this->assertSame(0, EdiEntry::count());
    }

    public function test_editing_existing_requires_admin(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1OLD',
            'locator' => 'JO70AA', 'email' => 'x@y.cz', 'qso_count' => 1, 'multiplier' => 1, 'points' => 1,
            'approved' => true,
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

        $this->assertSame(1, EdiEntry::count());

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertSessionHasErrors('znacka');

        $this->assertSame(1, EdiEntry::count());
    }

    public function test_submission_to_upcoming_round_is_blocked(): void
    {
        // Závod ještě nezačal (stav Nadcházející) → hlášení se nepřijímají.
        $kolo = EdiRound::create([
            'starts_at' => now()->addDays(5),
            'closes_at' => now()->addDays(10),
            'name' => 'Nadcházející kolo',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => 'A', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);

        $this->post('/hlaseni', $this->payload($kolo->id, $kat->id))
            ->assertSessionHasErrors('kolo');
        $this->assertSame(0, EdiEntry::count());
    }

    public function test_admin_can_edit_any_record(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1OLD',
            'locator' => 'JO70AA', 'email' => 'x@y.cz', 'qso_count' => 1, 'multiplier' => 1, 'points' => 1,
            'approved' => true,
        ]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $payload = $this->payload($kolo->id, $kat->id);
        $payload['id_zaznamu'] = $existing->id;

        $this->actingAs($admin)
            ->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame('OK2KJT', $existing->refresh()->callsign); // 'ok2kjt' → uppercase
    }

    public function test_session_owner_can_edit_their_own_record(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1OLD',
            'locator' => 'JO70AA', 'email' => 'x@y.cz', 'qso_count' => 1, 'multiplier' => 1, 'points' => 1,
            'approved' => true,
        ]);

        $payload = $this->payload($kolo->id, $kat->id);
        $payload['id_zaznamu'] = $existing->id;

        $this->withSession(['owned_data_id' => $existing->id])
            ->post('/hlaseni', $payload)
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame('OK2KJT', $existing->refresh()->callsign);
    }

    public function test_admin_routes_require_login(): void
    {
        $this->get('/admin/kategorie')->assertRedirect(route('login'));
    }

    public function test_anonymous_cannot_view_foreign_record_pii_via_id(): void
    {
        [$kolo, $kat] = $this->prepare();
        $existing = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1PII',
            'locator' => 'JO70AA', 'email' => 'secret@example.com', 'phone' => '+420123456789',
            'name' => 'Tajné Jméno', 'qso_count' => 1, 'multiplier' => 1, 'points' => 1, 'approved' => true,
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
        $existing = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1PII',
            'locator' => 'JO70AA', 'email' => 'secret@example.com', 'qso_count' => 1,
            'multiplier' => 1, 'points' => 1, 'approved' => true,
        ]);
        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('hlaseni.index', ['id' => $existing->id]))
            ->assertOk()
            ->assertSee('secret@example.com');
    }
}
