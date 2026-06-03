<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\ZaznamController;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin akce nad záznamem výsledkové listiny: P (schválit) a X (smazat).
 *
 * @see ZaznamController
 */
class ZaznamAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function zaznam(bool $schvaleno = false): VkvpaData
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDays(5),
            'datum_uzaverky' => now()->subDay(),
            'nazev' => '05/2026',
            'poznamka' => '',
        ]);
        $kat = VkvpaKategorie::create(['nazev' => '144 MHz single op', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);

        return VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1TEST',
            'locator' => 'JN99AJ', 'pocet' => 10, 'nasobice' => 5, 'body' => 50,
            'bodu_za_qso' => 0, 'schvaleno' => $schvaleno, 'odeslano' => false,
        ]);
    }

    public function test_admin_can_take_over_record(): void
    {
        $zaznam = $this->zaznam(false);

        $this->actingAs($this->admin())
            ->post(route('zaznam.prevzit', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $zaznam->id_kola]))
            ->assertSessionHas('announcement');

        // Po převzetí je záznam „převzatý" (schvaleno=true) → zmizí meruňkové pozadí.
        $this->assertTrue($zaznam->refresh()->schvaleno);
    }

    public function test_admin_can_delete_record(): void
    {
        $zaznam = $this->zaznam(true);

        $this->actingAs($this->admin())
            ->post(route('zaznam.smazat', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $zaznam->id_kola]))
            ->assertSessionHas('announcement');

        $this->assertDatabaseMissing('vkvpa_data', ['id' => $zaznam->id]);
    }

    public function test_guest_cannot_use_admin_actions(): void
    {
        $zaznam = $this->zaznam(false);

        $this->post(route('zaznam.prevzit', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('login'));

        $this->assertFalse($zaznam->refresh()->schvaleno);
    }
}
