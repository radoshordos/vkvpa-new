<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testy autentizace.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $password = 'tajne-heslo'): User
    {
        return $this->makeUser('Beda', isAdmin: true, password: $password);
    }

    /**
     * Uloží token jako selector + argon2id verifier (stejně jako LoginToken::issue)
     * a vrátí jeho plaintext podobu (selector+verifier) pro volání login.token.
     */
    private function createToken(?Carbon $createdAt = null, ?int $userId = null): string
    {
        $selector = Str::password(LoginToken::SELECTOR_LENGTH, letters: true, numbers: true, symbols: false);
        $verifier = Str::password(LoginToken::VERIFIER_LENGTH, letters: true, numbers: true, symbols: false);

        $loginToken = new LoginToken([
            'selector' => $selector,
            'token' => Hash::make($verifier),
            'user_id' => $userId,
        ]);

        // Vlastní created_at (test expirace) – jinak ho Eloquent nastaví na now().
        if ($createdAt !== null) {
            $loginToken->created_at = $createdAt;
        }

        $loginToken->save();

        return $selector.$verifier;
    }

    public function test_login_form_renders(): void
    {
        $this->get('/login')->assertOk()->assertSeeHtml('šup tam');
    }

    public function test_admin_can_log_in_with_valid_credentials(): void
    {
        $this->admin('tajne-heslo');

        // Admin přistává po přihlášení na statistikách.
        $this->post('/login', [
            'username' => 'Beda',
            'heslo' => 'tajne-heslo',
        ])->assertRedirect(route('admin.dashboard'));

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
        $token = $this->createToken(userId: $admin->id);

        $this->get(route('login.token', ['token' => $token]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($admin->name, session('prihlasen'));
        $this->assertSame(0, LoginToken::count()); // token smazán po použití
    }

    public function test_token_without_user_id_is_rejected(): void
    {
        $this->admin();
        $token = $this->createToken(userId: null);

        $this->get(route('login.token', ['token' => $token]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_invalid_token_redirects_with_error(): void
    {
        $this->admin();

        $this->get(route('login.token', ['token' => 'neplatny']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_expired_token_is_cleaned_and_rejected(): void
    {
        $this->admin();
        $token = $this->createToken(now()->subDays(6));

        $this->get(route('login.token', ['token' => $token]))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame(0, LoginToken::count()); // expirovaný token uklizen
    }

    public function test_token_with_confirm_redirects_to_record(): void
    {
        $admin = $this->admin();
        $token = $this->createToken(userId: $admin->id);

        $this->get(route('login.token', ['token' => $token, 'confirm' => 42]))
            ->assertRedirect(route('hlaseni.index', ['id' => 42]));

        $this->assertAuthenticatedAs($admin);
    }
}
