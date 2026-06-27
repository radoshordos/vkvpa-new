<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\UzivateleController;
use App\Models\EdiCategory;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin přehled kontaktních / osobních údajů závodníků z vkvpa_data.
 *
 * @see UzivateleController
 */
class UzivateleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private int $koloSeq = 0;

    /** @param  array<string, mixed>  $overrides */
    private function zaznam(array $overrides = []): VkvpaData
    {
        $this->koloSeq++;
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->subDays(2)->addMinutes($this->koloSeq),
            'datum_uzaverky' => now()->addDays(3),
            'nazev' => '05/2026',
            'poznamka' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz single op '.$this->koloSeq, 'band' => 'A'.$this->koloSeq, 'section' => 'SO', 'variant' => 'domestic']);

        return VkvpaData::create(array_merge([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1TEST',
            'locator' => 'JN99AJ', 'pocet' => 10, 'nasobice' => 5, 'body' => 50,
            'bodu_za_qso' => 0, 'schvaleno' => true, 'odeslano' => false,
            'jmeno' => 'Jan Novák', 'mail' => 'jan@example.com', 'telefon' => '+420 777 123 456',
        ], $overrides));
    }

    public function test_index_requires_admin(): void
    {
        $this->get(route('uzivatele.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_shows_contact_data_for_admin(): void
    {
        $this->zaznam();

        $this->actingAs($this->admin())
            ->get(route('uzivatele.index'))
            ->assertOk()
            ->assertSee('OK1TEST')
            ->assertSee('jan@example.com')
            ->assertSee('+420 777 123 456');
    }

    public function test_search_filters_records(): void
    {
        $this->zaznam(['znacka' => 'OK1AAA', 'mail' => 'aaa@example.com']);
        $this->zaznam(['znacka' => 'OK2BBB', 'mail' => 'bbb@example.com']);

        $this->actingAs($this->admin())
            ->get(route('uzivatele.index', ['q' => 'OK1AAA']))
            ->assertOk()
            ->assertSee('OK1AAA')
            ->assertDontSee('OK2BBB');
    }
}
