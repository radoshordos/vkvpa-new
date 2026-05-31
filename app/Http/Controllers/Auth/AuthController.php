<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VkvpaPrihlaseni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Autentizace administrace (Fáze 4).
 *
 * Nahrazuje:
 *  - hardcoded `Beda`/`oK1dOz` z head.php  → Auth::attempt + hashované heslo (S2)
 *  - interpolovaný `?kod=` lookup           → Eloquent s bindingem (S3)
 *  - `ereg()` validaci                      → validace Laravelu (S4)
 *  - logout.php (redirect na HTTP_REFERER)  → bezpečný redirect (žádný open-redirect)
 */
class AuthController extends Controller
{
    /** Dní platnosti přihlašovacího kódu (legacy: 5 dní). */
    private const int TOKEN_TTL_DAYS = 5;

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'heslo' => ['required', 'string'],
        ]);

        $ok = Auth::attempt(
            ['name' => $credentials['username'], 'password' => $credentials['heslo']],
            $request->boolean('remember'),
        );

        if (! $ok) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Přihlášení nějak nevyšlo, zkus to znova.']);
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Přihlášení přes jednorázový kód (legacy ?kod=). Bezpečně přes Eloquent.
     */
    public function loginViaToken(string $kod): RedirectResponse
    {
        // Úklid prošlých kódů (legacy: starší než 5 dní).
        VkvpaPrihlaseni::query()
            ->where('time', '<', Carbon::now()->subDays(self::TOKEN_TTL_DAYS))
            ->delete();

        $token = VkvpaPrihlaseni::query()->where('kod', $kod)->first();

        if ($token === null) {
            return redirect()->route('login')
                ->withErrors(['username' => 'Přihlašovací kód je neplatný nebo vypršel.']);
        }

        $admin = User::query()->where('is_admin', true)->first();

        if ($admin === null) {
            return redirect()->route('login')
                ->withErrors(['username' => 'Administrátorský účet není nastaven.']);
        }

        Auth::login($admin);
        $token->delete();

        request()->session()->regenerate();

        // „Převzít záznam" odkaz z e-mailu vyhodnocovateli (?confirm=ID).
        $confirm = (int) request()->integer('confirm');
        if ($confirm > 0) {
            return redirect()->route('edit_hlaseni', ['id' => $confirm]);
        }

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

}
