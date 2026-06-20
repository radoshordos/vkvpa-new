<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HesloControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $heslo = 'staresloheslo'): User
    {
        return User::create(['name' => 'Admin', 'password' => Hash::make($heslo), 'is_admin' => true]);
    }

    public function test_edit_renders_for_admin(): void
    {
        $this->actingAs($this->admin())
            ->get(route('heslo.edit'))
            ->assertOk();
    }

    public function test_edit_requires_admin(): void
    {
        $this->get(route('heslo.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_update_changes_password(): void
    {
        $admin = $this->admin('staresloheslo');

        $this->actingAs($admin)
            ->patch(route('heslo.update'), [
                'soucasne_heslo' => 'staresloheslo',
                'heslo' => 'novehesloheslo',
                'heslo_confirmation' => 'novehesloheslo',
            ])
            ->assertRedirect(route('heslo.edit'))
            ->assertSessionHas('announcement');

        $this->assertTrue(Hash::check('novehesloheslo', $admin->fresh()->password));
    }

    public function test_update_rejects_wrong_current_password(): void
    {
        $admin = $this->admin('staresloheslo');

        $this->actingAs($admin)
            ->patch(route('heslo.update'), [
                'soucasne_heslo' => 'spatneheslo',
                'heslo' => 'novehesloheslo',
                'heslo_confirmation' => 'novehesloheslo',
            ])
            ->assertSessionHasErrors('soucasne_heslo');

        $this->assertTrue(Hash::check('staresloheslo', $admin->fresh()->password));
    }

    public function test_update_requires_matching_confirmation(): void
    {
        $admin = $this->admin('staresloheslo');

        $this->actingAs($admin)
            ->patch(route('heslo.update'), [
                'soucasne_heslo' => 'staresloheslo',
                'heslo' => 'novehesloheslo',
                'heslo_confirmation' => 'jinepotvrzeni',
            ])
            ->assertSessionHasErrors('heslo');

        $this->assertTrue(Hash::check('staresloheslo', $admin->fresh()->password));
    }
}
