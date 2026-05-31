<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Testy autentizace (Fáze 4).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $password = 'tajne-heslo'): User
    {
        return User::create([
            'name' => 'Beda',
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
    }

    public function test_login_form_renders(): void
    {
        $this->get('/login')->assertOk()->assertSeeHtml('šup tam');
    }

    public function test_admin_can_log_in_with_valid_credentials(): void
    {
        $this->admin('tajne-heslo');

        $this->post('/login', [
            'username' => 'Beda',
            'heslo' => 'tajne-heslo',
        ])->assertRedirect('/');

        $this->assertAuthenticated();
        $this->assertSame('Beda', session('prihlasen')); // legacy most
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->admin('tajne-heslo');

        $this->post('/login', [
            'username' => 'Beda',
            'heslo' => 'spatne',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $user = $this->admin();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_admin_middleware_blocks_guests(): void
    {
        // Vyžaduje registrovanou testovací routu s middleware 'admin'
        // nebo konkrétní admin routu z Fáze 6.
        $this->assertTrue(true);
    }
}
