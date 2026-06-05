<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VkvpaKategorie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KategorieControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    // ------------------------------------------------------------------
    // index

    public function test_index_renders_for_admin(): void
    {
        VkvpaKategorie::create(['nazev' => '144 MHz single op', 'popis' => '', 'zkratka' => '144 SO', 'dxid' => 0]);

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

    // ------------------------------------------------------------------
    // store – úspěšné vytvoření

    public function test_store_creates_kategorie(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => '144 MHz single op',
                'popis' => 'Popis kategorie',
                'zkratka' => '144 SO',
                'dxid' => 0,
            ])
            ->assertRedirect(route('kategorie.index'))
            ->assertSessionHas('announcement');

        $this->assertDatabaseHas('vkvpa_kategorie', [
            'nazev' => '144 MHz single op',
            'zkratka' => '144 SO',
            'dxid' => 0,
        ]);
    }

    public function test_store_creates_dx_kategorie_with_nonzero_dxid(): void
    {
        $domestic = VkvpaKategorie::create(['nazev' => '144 MHz single op', 'popis' => '', 'zkratka' => '144 SO', 'dxid' => 0]);

        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => '144 MHz single DX',
                'popis' => '',
                'zkratka' => '144 SO DX',
                'dxid' => $domestic->id,
            ])
            ->assertRedirect(route('kategorie.index'));

        $this->assertDatabaseHas('vkvpa_kategorie', [
            'nazev' => '144 MHz single DX',
            'dxid' => $domestic->id,
        ]);
    }

    public function test_store_allows_empty_popis(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => 'Test',
                'popis' => '',
                'zkratka' => 'T',
                'dxid' => 0,
            ])
            ->assertRedirect(route('kategorie.index'));

        $this->assertDatabaseHas('vkvpa_kategorie', ['nazev' => 'Test']);
    }

    // ------------------------------------------------------------------
    // store – validace

    public function test_store_requires_nazev(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'popis' => '',
                'zkratka' => 'X',
                'dxid' => 0,
            ])
            ->assertSessionHasErrors('nazev');
    }

    public function test_store_requires_zkratka(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => 'Test',
                'popis' => '',
                'dxid' => 0,
            ])
            ->assertSessionHasErrors('zkratka');
    }

    public function test_store_requires_dxid(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => 'Test',
                'popis' => '',
                'zkratka' => 'T',
            ])
            ->assertSessionHasErrors('dxid');
    }

    public function test_store_rejects_negative_dxid(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => 'Test',
                'popis' => '',
                'zkratka' => 'T',
                'dxid' => -1,
            ])
            ->assertSessionHasErrors('dxid');
    }

    public function test_store_rejects_nazev_too_long(): void
    {
        $this->actingAs($this->admin())
            ->post(route('kategorie.store'), [
                'nazev' => str_repeat('A', 51),
                'popis' => '',
                'zkratka' => 'T',
                'dxid' => 0,
            ])
            ->assertSessionHasErrors('nazev');
    }

    public function test_store_requires_admin(): void
    {
        $this->post(route('kategorie.store'), [
            'nazev' => 'Test',
            'popis' => '',
            'zkratka' => 'T',
            'dxid' => 0,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('vkvpa_kategorie', ['nazev' => 'Test']);
    }
}
