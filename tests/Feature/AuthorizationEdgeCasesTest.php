<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hraniční případy autorizace:
 *  - přihlášený ne-admin na admin routách
 *  - vlastník session editující cizí záznam
 *  - nulový session token
 *  - pokus o editaci neexistujícího záznamu
 */
class AuthorizationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function nonAdmin(): User
    {
        return User::create(['name' => 'Uzivatel', 'password' => Hash::make('x'), 'is_admin' => false]);
    }

    private function zaznam(): EdiEntry
    {
        $kolo = EdiRound::create([
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'name' => 'Testovací kolo',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz SO', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);

        return EdiEntry::create([
            'round_id' => $kolo->id,
            'category_id' => $kat->id,
            'callsign' => 'OK1CIZI',
            'locator' => 'JO70AA',
            'email' => 'cizi@example.com',
            'qso_count' => 5,
            'multiplier' => 3,
            'points' => 15,
            'approved' => true,
        ]);
    }

    // ------------------------------------------------------------------
    // Přihlášený ne-admin na admin routách → přesměrování na /login

    public function test_authenticated_non_admin_redirected_from_admin_get_routes(): void
    {
        $this->actingAs($this->nonAdmin());

        foreach (['/admin/kategorie', '/admin/deniky', '/admin/edi-debug', '/admin/importy'] as $url) {
            $this->get($url)
                ->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_non_admin_cannot_approve_record(): void
    {
        $zaznam = $this->zaznam();

        $this->actingAs($this->nonAdmin())
            ->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('login'));

        // Stav záznamu se nezměnil.
        $this->assertTrue((bool) $zaznam->refresh()->approved);
    }

    public function test_authenticated_non_admin_cannot_delete_record(): void
    {
        $zaznam = $this->zaznam();

        $this->actingAs($this->nonAdmin())
            ->delete(route('zaznam.destroy', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('edi_entries', ['id' => $zaznam->id]);
    }

    // ------------------------------------------------------------------
    // Session vlastník editující cizí záznam → 403

    public function test_session_owner_cannot_edit_record_they_dont_own(): void
    {
        $cizi = $this->zaznam();

        // Session má owned_data_id = jiné ID (simulujeme jiný záznam, který si vytvořili).
        $vlastni = EdiEntry::create([
            'round_id' => $cizi->round_id,
            'category_id' => $cizi->category_id,
            'callsign' => 'OK1MOJE',
            'locator' => 'JN99AJ',
            'email' => 'moje@example.com',
            'qso_count' => 1,
            'multiplier' => 1,
            'points' => 1,
            'approved' => false,
        ]);

        // Session říká: vlastním $vlastni, ale formulář posílá id_zaznamu = $cizi.
        $this->withSession(['owned_data_id' => $vlastni->id])
            ->post('/hlaseni', [
                'id_zaznamu' => $cizi->id,
                'kolo' => $cizi->round_id,
                'kategorie' => $cizi->category_id,
                'callsign' => 'OK1UTOCNIK',
                'locator' => 'JN99AJ',
                'email' => 'utok@example.com',
            ])
            ->assertForbidden();

        $this->assertSame('OK1CIZI', $cizi->refresh()->callsign, 'Cizí záznam nesmí být změněn');
    }

    public function test_session_with_no_owned_id_cannot_edit_existing_record(): void
    {
        $cizi = $this->zaznam();

        // Session nemá owned_data_id (nebo je 0).
        $this->withSession([])
            ->post('/hlaseni', [
                'id_zaznamu' => $cizi->id,
                'kolo' => $cizi->round_id,
                'kategorie' => $cizi->category_id,
                'callsign' => 'OK1UTOCNIK',
                'locator' => 'JN99AJ',
                'email' => 'utok@example.com',
            ])
            ->assertForbidden();

        $this->assertSame('OK1CIZI', $cizi->refresh()->callsign);
    }

    public function test_anonymous_cannot_edit_any_existing_record(): void
    {
        $zaznam = $this->zaznam();

        $this->post('/hlaseni', [
            'id_zaznamu' => $zaznam->id,
            'kolo' => $zaznam->round_id,
            'kategorie' => $zaznam->category_id,
            'callsign' => 'OK1UTOCNIK',
            'locator' => 'JN99AJ',
            'email' => 'utok@example.com',
        ])->assertForbidden();

        $this->assertSame('OK1CIZI', $zaznam->refresh()->callsign);
    }

    // ------------------------------------------------------------------
    // Nový záznam (id_zaznamu=0) je povolen komukoliv

    public function test_anyone_can_create_new_record_without_session(): void
    {
        $kolo = EdiRound::create([
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDays(5),
            'name' => 'Aktivní kolo',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => 'A', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);

        $this->post('/hlaseni', [
            'kolo' => $kolo->id,
            'kategorie' => $kat->id,
            'znacka' => 'OK1NOVY',
            'locator' => 'JN99AJ',
            'jmeno' => 'Nový Závodník',
            'email' => 'novy@example.com',
            'telefon' => '+420 777 123 456',
        ])->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, EdiEntry::count());
    }

    // ------------------------------------------------------------------
    // Zamítnutý přístup k EDI debug pro ne-admina

    public function test_guest_cannot_access_edi_debug(): void
    {
        $this->get(route('edi.debug.create'))
            ->assertRedirect(route('login'));

        $this->post(route('edi.debug.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_edi_debug(): void
    {
        $this->actingAs($this->nonAdmin())
            ->get(route('edi.debug.create'))
            ->assertRedirect(route('login'));
    }
}
