<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Routy autentizace (Fáze 4).
 * Vlož do routes/web.php (nebo načti přes require __DIR__.'/auth.php';).
 *
 * Fáze 6: na tyto pojmenované routy se přepnou odkazy v menu
 * (logout.php → route('logout'), ?str=login → route('login')).
 */

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Legacy přihlášení přes jednorázový kód (?kod=) – nyní bezpečně:
Route::get('/login/token/{kod}', [AuthController::class, 'loginViaToken'])
    ->name('login.token')
    ->where('kod', '[A-Za-z0-9]+');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
 * Příklad ochrany administrace (Fáze 6 napojí konkrétní stránky):
 *
 * Route::middleware('admin')->group(function () {
 *     Route::get('/admin/kola', [KolaController::class, 'index'])->name('admin.kola');
 *     // …
 * });
 */
