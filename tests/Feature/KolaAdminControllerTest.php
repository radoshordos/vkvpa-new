<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\KolaAdminController;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin CRUD pro kola závodu.
 *
 * @see KolaAdminController
 */
class KolaAdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    /** @param array<string, mixed> $overrides */
    private function makeKolo(array $overrides = []): EdiRound
    {
        return EdiRound::create(array_merge([
            'starts_at' => '2026-01-17 08:00:00',
            'closes_at' => '2026-02-01 23:59:00',
            'name' => 'Testovací kolo',
            'note' => '',
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // create form

    public function test_create_form_renders_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('kola.admin.create'))
            ->assertOk()
            ->assertSee('Přidat nové kolo');
    }

    public function test_create_form_requires_admin(): void
    {
        $this->get(route('kola.admin.create'))
            ->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // store

    public function test_store_creates_new_kolo(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kola.admin.store'), [
                'name' => 'Nové kolo 2026',
                'starts_at' => '2026-04-19T08:00',
                'closes_at' => '2026-05-03T23:59',
                'note' => '',
            ])
            ->assertRedirect(route('kola.admin.index'))
            ->assertSessionHas('announcement');

        $this->assertDatabaseHas('edi_rounds', [
            'name' => 'Nové kolo 2026',
        ]);
        // Čas z datetime-local pole se uloží jako start závodu.
        $this->assertSame(
            '2026-04-19 08:00:00',
            EdiRound::where('name', 'Nové kolo 2026')->firstOrFail()->starts_at->toDateTimeString(),
        );
    }

    public function test_store_requires_admin(): void
    {
        $this->post(route('kola.admin.store'), [
            'name' => 'Neautorizované kolo',
            'starts_at' => '2026-04-19T08:00',
            'closes_at' => '2026-05-03T23:59',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('edi_rounds', ['name' => 'Neautorizované kolo']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kola.admin.store'), [])
            ->assertSessionHasErrors(['name', 'starts_at', 'closes_at']);
    }

    // ------------------------------------------------------------------
    // edit form

    public function test_edit_form_renders_with_existing_data(): void
    {
        $kolo = $this->makeKolo(['name' => 'Kolo pro editaci']);

        $this->actingAs($this->admin())
            ->get(route('kola.admin.edit', $kolo->id))
            ->assertOk()
            ->assertSee('Kolo pro editaci');
    }

    public function test_edit_form_requires_admin(): void
    {
        $kolo = $this->makeKolo();

        $this->get(route('kola.admin.edit', $kolo->id))
            ->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // update

    public function test_update_saves_changes(): void
    {
        $kolo = $this->makeKolo(['name' => 'Původní název']);

        $this->actingAs($this->admin())
            ->patch(route('kola.admin.update', $kolo->id), [
                'name' => 'Nový název kola',
                // Start lze posunout nejvýše o 7 dní oproti původnímu termínu
                // (původní = 2026-01-17), viz KoloRequest::startPosunRule().
                'starts_at' => '2026-01-18T08:00',
                'closes_at' => '2026-02-01T23:59',
                'note' => 'Poznámka',
            ])
            ->assertRedirect(route('kola.admin.index'))
            ->assertSessionHas('announcement');

        $this->assertDatabaseHas('edi_rounds', [
            'id' => $kolo->id,
            'name' => 'Nový název kola',
        ]);
    }

    public function test_update_requires_admin(): void
    {
        $kolo = $this->makeKolo();

        $this->patch(route('kola.admin.update', $kolo->id), [
            'name' => 'Neoprávněná změna',
            'starts_at' => '2026-04-19T08:00',
            'closes_at' => '2026-05-03T23:59',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('edi_rounds', ['name' => 'Neoprávněná změna']);
    }

    public function test_update_validates_required_fields(): void
    {
        $kolo = $this->makeKolo();

        $this->actingAs($this->admin())
            ->patch(route('kola.admin.update', $kolo->id), [])
            ->assertSessionHasErrors(['name', 'starts_at', 'closes_at']);
    }

    // ------------------------------------------------------------------
    // kola.admin.index – admin vidí tlačítka

    public function test_admin_sees_create_button_and_edit_links_on_kola_page(): void
    {
        $kolo = $this->makeKolo(['name' => 'Kolo 2026/01']);

        $this->actingAs($this->admin())
            ->get(route('kola.admin.index'))
            ->assertOk()
            ->assertSee(route('kola.admin.create'))
            ->assertSee(route('kola.admin.edit', $kolo->id));
    }

    public function test_guest_cannot_access_kola_page(): void
    {
        $this->makeKolo();

        // Výpis kol je nově jen pro admina; staré /kola přesměruje na admin verzi.
        $this->get('/kola')->assertRedirect('/admin/kola');
        $this->get(route('kola.admin.index'))->assertRedirect(route('login'));
    }
}
