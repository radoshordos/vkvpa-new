<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiBand;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return $this->makeUser('Admin', isAdmin: true);
    }

    /**
     * Validní data formuláře (edi_categories). Lze přepsat jednotlivé klíče.
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

    /** id pásma z číselníku podle názvu ('47 GHz' → edi_bands.id). */
    private function bandId(string $name): int
    {
        $id = EdiBand::query()->where('name', $name)->value('id');

        return is_int($id) ? $id : 0;
    }

    /**
     * Vytvoří kategorii přímo (mimo HTTP) – přeloží `band` název na `band_id`
     * (textový sloupec band už neexistuje).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCategory(array $overrides = []): EdiCategory
    {
        $data = $this->payload($overrides);
        $band = $data['band'];
        $data['band_id'] = $this->bandId(is_string($band) ? $band : '');
        unset($data['band']);

        return EdiCategory::create($data);
    }

    // ------------------------------------------------------------------
    // index

    public function test_index_renders_for_admin(): void
    {
        $this->createCategory(['band' => '47 GHz']);

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
        $kolo = EdiRound::create([
            'name' => '06/2026',
            'note' => '',
            'evaluated_at' => null,
            'starts_at' => '2026-06-21 08:00:00',
            'closes_at' => '2026-06-26 23:59:59',
        ]);
        $kat = $this->createCategory(['name' => '144 MHz SO', 'band' => '47 GHz']);

        foreach (['OK1AAA', 'OK1BBB', 'OK1CCC'] as $znacka) {
            EdiEntry::create([
                'round_id' => $kolo->id,
                'category_id' => $kat->id,
                'callsign' => $znacka,
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

        $this->assertDatabaseHas('edi_categories', [
            'name' => '144 MHz single op',
            'band_id' => $this->bandId('47 GHz'),
            'section' => 'SO',
            'variant' => 'domestic',
        ]);
    }

    public function test_store_creates_dx_kategorie_with_dxid(): void
    {
        $domestic = $this->createCategory(['band' => '47 GHz']);

        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), $this->payload([
                'name' => '47 GHz single op DX',
                'band' => '47 GHz',
                'variant' => 'dx',
                'dxid' => $domestic->id,
            ]))
            ->assertRedirect(route('kategorie.index'));

        $this->assertDatabaseHas('edi_categories', [
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
        $this->createCategory(['band' => '47 GHz']);

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

        $this->assertDatabaseMissing('edi_categories', ['band_id' => $this->bandId('47 GHz')]);
    }

    // ------------------------------------------------------------------
    // edit / update

    public function test_edit_renders_form_with_existing_data(): void
    {
        $kat = $this->createCategory(['name' => 'Mistrovska kategorie', 'band' => '47 GHz']);

        $this->actingAs($this->admin())
            ->get(route('kategorie.edit', $kat->id))
            ->assertOk()
            ->assertSee('Mistrovska kategorie');
    }

    public function test_edit_requires_admin(): void
    {
        $kat = $this->createCategory(['band' => '47 GHz']);

        $this->get(route('kategorie.edit', $kat->id))
            ->assertRedirect(route('login'));
    }

    public function test_update_saves_changes(): void
    {
        $kat = $this->createCategory(['name' => 'Stary nazev', 'band' => '47 GHz']);

        $this->actingAs($this->admin())
            ->patch(route('kategorie.update', $kat->id), $this->payload([
                'name' => 'Nový název',
                'band' => '76 GHz',
            ]))
            ->assertRedirect(route('kategorie.index'))
            ->assertSessionHas('announcement');

        $this->assertDatabaseHas('edi_categories', [
            'id' => $kat->id,
            'name' => 'Nový název',
            'band_id' => $this->bandId('76 GHz'),
        ]);
    }

    public function test_update_requires_admin(): void
    {
        $kat = $this->createCategory(['band' => '47 GHz']);

        $this->patch(route('kategorie.update', $kat->id), $this->payload(['name' => 'Zmeneno', 'band' => '47 GHz']))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('edi_categories', ['name' => 'Zmeneno']);
    }

    public function test_update_validates_required_fields(): void
    {
        $kat = $this->createCategory(['band' => '47 GHz']);

        $this->actingAs($this->admin())
            ->patch(route('kategorie.update', $kat->id), $this->payload([
                'name' => '',
                'band' => '',
                'section' => '',
            ]))
            ->assertSessionHasErrors(['name', 'band', 'section']);
    }
}
