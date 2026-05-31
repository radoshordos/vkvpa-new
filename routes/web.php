<?php

declare(strict_types=1);

use App\Http\Controllers\MailImageController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\Admin\VyhodnoceniController;
use App\Http\Controllers\EdiController;
use App\Http\Controllers\HlaseniController;
use App\Http\Controllers\KolaController;
use App\Http\Controllers\Admin\DenikyController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\KategorieController;
use App\Http\Controllers\VysledkyController;
use Illuminate\Support\Facades\Route;

/*
 * Routy aplikace (Fáze 6) – nahrazují index.php ?str= whitelist.
 * Legacy klíč ?str=X  →  pojmenovaná routa (name odpovídá klíči).
 */

require __DIR__ . '/auth.php'; // Fáze 4

// Výchozí stránka = formulář hlášení (legacy default $_GET['str']='edit_hlaseni').
Route::get('/', [HlaseniController::class, 'index'])->name('edit_hlaseni');

// --- Veřejné ---
Route::get('/kola', [KolaController::class, 'index'])->name('edit_kola');

Route::get('/hlaseni', [HlaseniController::class, 'index'])->name('hlaseni.index');
Route::post('/hlaseni', [HlaseniController::class, 'store'])->name('hlaseni.store');

Route::get('/vysledky', [VysledkyController::class, 'listina'])->name('vysledkova_listina');
Route::get('/vysledky/rocni', [VysledkyController::class, 'rocni'])->name('rocni_vysledky');

// Obfuskovaný e-mail jako obrázek (footer) – nahrazuje mail.php
Route::get('/mail-image', [MailImageController::class, 'show'])->name('mail.image');

// Nahrání EDI deníku (využívá EdiParser/EdiImportService z Fáze 5).
Route::get('/edi', [EdiController::class, 'create'])->name('read_edi');
Route::post('/edi', [EdiController::class, 'store'])->name('read_edi.store');

// Mapa spojení stanice (Fáze 9) – sjednocuje map*.php
Route::get('/edi/{head}/mapa', [MapController::class, 'show'])->name('edi.mapa');

// --- Administrace (chráněno middleware z Fáze 4) ---
Route::middleware('admin')->group(function (): void {
    // Vyhodnocení a uzávěrka kola (Fáze 7)
    Route::post('/admin/kolo/{kolo}/vyhodnotit', [VyhodnoceniController::class, 'vyhodnotit'])->name('kolo.vyhodnotit');
    Route::post('/admin/kolo/{kolo}/uzavrit', [VyhodnoceniController::class, 'uzavrit'])->name('kolo.uzavrit');

    // (Editace hlášení je nyní přes ?id na stránce hlášení; uložení přes hlaseni.store.)

    Route::get('/admin/deniky', [DenikyController::class, 'index'])->name('edit_deniky');
    Route::get('/admin/kategorie', [KategorieController::class, 'index'])->name('edit_kategorie');
    Route::get('/admin/importy', [ImportController::class, 'index'])->name('edit_import');
});
