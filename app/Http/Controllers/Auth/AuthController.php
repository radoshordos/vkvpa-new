<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VkvpaPrihlaseni;
use App\Support\VkvpaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Autentizace administrace.
 */
class AuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'heslo' => ['required', 'string'],
        ]);

        $username = $request->string('username')->value();
        $heslo = $request->string('heslo')->value();

        $ok = Auth::attempt(
            ['name' => $username, 'password' => $heslo],
            $request->boolean('remember'),
        );

        if (! $ok) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Přihlášení nějak nevyšlo, zkus to znova.']);
        }

        $request->session()->regenerate();
        $request->session()->put('prihlasen', $username);

        return redirect()->intended('/');
    }

    /**
     * Přihlášení přes jednorázový kód (?kod=).
     */
    public function loginViaToken(string $kod, Request $request): RedirectResponse
    {
        VkvpaPrihlaseni::query()
            ->where('time', '<', Carbon::now()->subDays(VkvpaSettings::tokenTtlDays()))
            ->delete();

        // lockForUpdate + delete v transakci: paralelní požadavky (prefetch prohlížeče,
        // dvojité kliknutí) nemohou použít stejný token dvakrát.
        $used = DB::transaction(function () use ($kod): bool {
            $token = VkvpaPrihlaseni::query()->where('kod', hash('sha256', $kod))->lockForUpdate()->first();
            if ($token === null) {
                return false;
            }
            $token->delete();

            return true;
        });

        if (! $used) {
            return redirect()->route('login')
                ->withErrors(['username' => 'Přihlašovací kód je neplatný nebo vypršel.']);
        }

        $admin = User::query()->where('is_admin', true)->first();

        if ($admin === null) {
            return redirect()->route('login')
                ->withErrors(['username' => 'Administrátorský účet není nastaven.']);
        }

        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->put('prihlasen', $admin->name);

        // „Převzít záznam" odkaz z e-mailu vyhodnocovateli (?confirm=ID).
        $confirm = $request->integer('confirm');
        if ($confirm > 0) {
            return redirect()->route('hlaseni.index', ['id' => $confirm]);
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
