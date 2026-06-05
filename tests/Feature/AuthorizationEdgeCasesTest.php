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

    private function zaznam(): VkvpaData
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Testovací kolo',
            'aktivni' => true,
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz SO', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        return VkvpaData::create([
            'id_kola' => $kolo->id,
            'id_kategorie' => $kat->id,
            'znacka' => 'OK1CIZI',
            'locator' => 'JO70AA',
            'mail' => 'cizi@example.com',
            'pocet' => 5,
            'nasobice' => 3,
            'body' => 15,
            'schvaleno' => true,
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
        $this->assertTrue((bool) $zaznam->refresh()->schvaleno);
    }

    public function test_authenticated_non_admin_cannot_delete_record(): void
    {
        $zaznam = $this->zaznam();

        $this->actingAs($this->nonAdmin())
            ->delete(route('zaznam.destroy', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('vkvpa_data', ['id' => $zaznam->id]);
    }

    // ------------------------------------------------------------------
    // Session vlastník editující cizí záznam → 403

    public function test_session_owner_cannot_edit_record_they_dont_own(): void
    {
        $cizi = $this->zaznam();

        // Session má owned_data_id = jiné ID (simulujeme jiný záznam, který si vytvořili).
        $vlastni = VkvpaData::create([
            'id_kola' => $cizi->id_kola,
            'id_kategorie' => $cizi->id_kategorie,
            'znacka' => 'OK1MOJE',
            'locator' => 'JN99AJ',
            'mail' => 'moje@example.com',
            'pocet' => 1,
            'nasobice' => 1,
            'body' => 1,
            'schvaleno' => false,
        ]);

        // Session říká: vlastním $vlastni, ale formulář posílá id_zaznamu = $cizi.
        $this->withSession(['owned_data_id' => $vlastni->id])
            ->post('/hlaseni', [
                'id_zaznamu' => $cizi->id,
                'kolo' => $cizi->id_kola,
                'kategorie' => $cizi->id_kategorie,
                'znacka' => 'OK1UTOCNIK',
                'locator' => 'JN99AJ',
                'email' => 'utok@example.com',
            ])
            ->assertForbidden();

        $this->assertSame('OK1CIZI', $cizi->refresh()->znacka, 'Cizí záznam nesmí být změněn');
    }

    public function test_session_with_no_owned_id_cannot_edit_existing_record(): void
    {
        $cizi = $this->zaznam();

        // Session nemá owned_data_id (nebo je 0).
        $this->withSession([])
            ->post('/hlaseni', [
                'id_zaznamu' => $cizi->id,
                'kolo' => $cizi->id_kola,
                'kategorie' => $cizi->id_kategorie,
                'znacka' => 'OK1UTOCNIK',
                'locator' => 'JN99AJ',
                'email' => 'utok@example.com',
            ])
            ->assertForbidden();

        $this->assertSame('OK1CIZI', $cizi->refresh()->znacka);
    }

    public function test_anonymous_cannot_edit_any_existing_record(): void
    {
        $zaznam = $this->zaznam();

        $this->post('/hlaseni', [
            'id_zaznamu' => $zaznam->id,
            'kolo' => $zaznam->id_kola,
            'kategorie' => $zaznam->id_kategorie,
            'znacka' => 'OK1UTOCNIK',
            'locator' => 'JN99AJ',
            'email' => 'utok@example.com',
        ])->assertForbidden();

        $this->assertSame('OK1CIZI', $zaznam->refresh()->znacka);
    }

    // ------------------------------------------------------------------
    // Nový záznam (id_zaznamu=0) je povolen komukoliv

    public function test_anyone_can_create_new_record_without_session(): void
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDays(5),
            'nazev' => 'Aktivní kolo',
            'aktivni' => true,
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => 'A', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        $this->post('/hlaseni', [
            'kolo' => $kolo->id,
            'kategorie' => $kat->id,
            'znacka' => 'OK1NOVY',
            'locator' => 'JN99AJ',
            'email' => 'novy@example.com',
        ])->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]));

        $this->assertSame(1, VkvpaData::count());
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
