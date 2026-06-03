<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VkvpaPrihlaseni;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Testy autentizace.
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
        $this->assertSame('Beda', session('prihlasen'));
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
        // Vyžaduje registrovanou testovací routu s middleware 'admin'.
        $this->expectNotToPerformAssertions();
    }

    public function test_valid_token_logs_in_admin(): void
    {
        $admin = $this->admin();
        VkvpaPrihlaseni::create(['time' => now(), 'kod' => 'abc123']);

        $this->get(route('login.token', ['kod' => 'abc123']))
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($admin->name, session('prihlasen'));
        $this->assertSame(0, VkvpaPrihlaseni::count()); // token smazán po použití
    }

    public function test_invalid_token_redirects_with_error(): void
    {
        $this->admin();

        $this->get(route('login.token', ['kod' => 'neplatny']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_expired_token_is_cleaned_and_rejected(): void
    {
        $this->admin();
        VkvpaPrihlaseni::create(['time' => now()->subDays(6), 'kod' => 'stary123']);

        $this->get(route('login.token', ['kod' => 'stary123']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame(0, VkvpaPrihlaseni::count()); // expirovaný token uklizen
    }

    public function test_token_with_confirm_redirects_to_record(): void
    {
        $admin = $this->admin();
        VkvpaPrihlaseni::create(['time' => now(), 'kod' => 'xyz789']);

        $this->get(route('login.token', ['kod' => 'xyz789', 'confirm' => 42]))
            ->assertRedirect(route('edit_hlaseni', ['id' => 42]));

        $this->assertAuthenticatedAs($admin);
    }
}
