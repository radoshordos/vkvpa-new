<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KategorieControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // CRUD testujeme na čistém číselníku (TestCase jinak seeduje 42 kategorií,
        // což by kolidovalo s unikátem band+section+variant).
        EdiCategory::query()->delete();
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    /**
     * Validní data formuláře (edi_category). Lze přepsat jednotlivé klíče.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '144 MHz single op',
            'band' => '144 MHz',
            'section' => 'SO',
            'variant' => 'domestic',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // index

    public function test_index_renders_for_admin(): void
    {
        EdiCategory::create($this->payload(['band' => '47 GHz']));

        $this->actingAs($this->admin())
            ->get(route('kategorie.index'))
            ->assertOk()
            ->assertSee('144 MHz single op');
    }

    public function test_index_requires_admin(): void
    {
        $this->get(route('kategorie.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_shows_usage_count_per_category_and_total(): void
    {
        $kolo = VkvpaKola::create([
            'nazev' => '06/2026',
            'poznamka' => '',
            'vyhodnoceno' => null,
            'datum_konani' => '2026-06-21 08:00:00',
            'datum_uzaverky' => '2026-06-26 23:59:59',
        ]);
        $kat = EdiCategory::create($this->payload(['name' => '144 MHz SO', 'band' => '47 GHz']));

        foreach (['OK1AAA', 'OK1BBB', 'OK1CCC'] as $znacka) {
            VkvpaData::create([
                'id_kola' => $kolo->id,
                'id_kategorie' => $kat->id,
                'znacka' => $znacka,
                'locator' => 'JN79XX',
            ]);
        }

        $this->actingAs($this->admin())
            ->get(route('kategorie.index'))
            ->assertOk()
            ->assertSee(__('admin.kategorie_col_count'))
            ->assertSee('144 MHz SO')
            ->assertSee(__('admin.kategorie_total'));
    }

    // ------------------------------------------------------------------
    // store – úspěšné vytvoření

    public function test_store_creates_kategorie(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload(['band' => '47 GHz']))
            ->assertRedirect(route('kategorie.index'))
            ->assertSessionHas('announcement');

        $this->assertDatabaseHas('edi_category', [
            'name' => '144 MHz single op',
            'band' => '47 GHz',
            'section' => 'SO',
            'variant' => 'domestic',
        ]);
    }

    public function test_store_creates_dx_kategorie_with_dxid(): void
    {
        $domestic = EdiCategory::create($this->payload(['band' => '47 GHz']));

        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload([
                'name' => '47 GHz single op DX',
                'band' => '47 GHz',
                'variant' => 'dx',
                'dxid' => $domestic->id,
            ]))
            ->assertRedirect(route('kategorie.index'));

        $this->assertDatabaseHas('edi_category', [
            'name' => '47 GHz single op DX',
            'variant' => 'dx',
            'dxid' => $domestic->id,
        ]);
    }

    // ------------------------------------------------------------------
    // store – validace

    public function test_store_requires_name(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload(['name' => '', 'band' => '47 GHz']))
            ->assertSessionHasErrors('name');
    }

    public function test_store_requires_valid_band(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload(['band' => 'Neexistuje']))
            ->assertSessionHasErrors('band');
    }

    public function test_store_requires_valid_section(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload(['section' => 'XX', 'band' => '47 GHz']))
            ->assertSessionHasErrors('section');
    }

    public function test_store_rejects_duplicate_band_section_variant(): void
    {
        EdiCategory::create($this->payload(['band' => '47 GHz']));

        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload(['band' => '47 GHz']))
            ->assertSessionHasErrors('variant');
    }

    public function test_store_rejects_name_too_long(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload(['name' => str_repeat('A', 51), 'band' => '47 GHz']))
            ->assertSessionHasErrors('name');
    }

    public function test_store_requires_admin(): void
    {
        $this->post(route('kategorie.store'), $this->payload(['band' => '47 GHz']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('edi_category', ['band' => '47 GHz']);
    }

    // ------------------------------------------------------------------
    // edit / update

    public function test_edit_renders_form_with_existing_data(): void
    {
        $kat = EdiCategory::create($this->payload(['name' => 'Mistrovska kategorie', 'band' => '47 GHz']));

        $this->actingAs($this->admin())
            ->get(route('kategorie.edit', $kat->id))
            ->assertOk()
            ->assertSee('Mistrovska kategorie');
    }

    public function test_edit_requires_admin(): void
    {
        $kat = EdiCategory::create($this->payload(['band' => '47 GHz']));

        $this->get(route('kategorie.edit', $kat->id))
            ->assertRedirect(route('login'));
    }

    public function test_update_saves_changes(): void
    {
        $kat = EdiCategory::create($this->payload(['name' => 'Stary nazev', 'band' => '47 GHz']));

        $this->actingAs($this->admin())
            ->patch(route('kategorie.update', $kat->id), $this->payload([
                'name' => 'Nový název',
                'band' => '76 GHz',
            ]))
            ->assertRedirect(route('kategorie.index'))
            ->assertSessionHas('announcement');

        $this->assertDatabaseHas('edi_category', [
            'id' => $kat->id,
            'name' => 'Nový název',
            'band' => '76 GHz',
        ]);
    }

    public function test_update_requires_admin(): void
    {
        $kat = EdiCategory::create($this->payload(['band' => '47 GHz']));

        $this->patch(route('kategorie.update', $kat->id), $this->payload(['name' => 'Zmeneno', 'band' => '47 GHz']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('edi_category', ['name' => 'Zmeneno']);
    }

    public function test_update_validates_required_fields(): void
    {
        $kat = EdiCategory::create($this->payload(['band' => '47 GHz']));

        $this->actingAs($this->admin())
            ->patch(route('kategorie.update', $kat->id), $this->payload([
                'name' => '',
                'band' => '',
                'section' => '',
            ]))
            ->assertSessionHasErrors(['name', 'band', 'section']);
    }
}
