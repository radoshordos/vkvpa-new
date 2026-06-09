<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Routy autentizace (načítané z routes/web.php).
 */

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Přihlášení přes jednorázový alfanumerický kód (token login, TTL dle vkvpa.token_ttl_days).
Route::get('/login/token/{kod}', [AuthController::class, 'loginViaToken'])
    ->name('login.token')
    ->middleware('throttle:login-token')
    ->where('kod', '[A-Za-z0-9]+');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
