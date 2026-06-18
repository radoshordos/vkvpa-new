<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DenikyController;
use App\Http\Controllers\Admin\EdiDebugController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\KategorieController;
use App\Http\Controllers\Admin\KolaAdminController;
use App\Http\Controllers\Admin\UzivateleController;
use App\Http\Controllers\Admin\ZaznamController;
use App\Http\Controllers\DiskuseController;
use App\Http\Controllers\EdiController;
use App\Http\Controllers\EdiPorovnaniController;
use App\Http\Controllers\EdiVizualizaceController;
use App\Http\Controllers\HlaseniController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailImageController;
use App\Http\Controllers\VysledkyController;
use Illuminate\Support\Facades\Route;

/*
 * Routy aplikace – pojmenované routy aplikace.
 */

require __DIR__.'/auth.php';

// Přepínání jazyka (cs / en) – ukládá volbu do session.
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['cs', 'en'], true)) {
        session(['locale' => $locale]);
    }

    // Jen interní cesta z předchozí URL (bez hostu, fallback '/') – plné
    // `back()` přebírá hlavičku Referer a umožnilo by open redirect na cizí web.
    return redirect(url()->previousPath());
})->name('lang.switch');

// Výchozí stránka = úvodní obrazovka.
Route::get('/', [HomeController::class, 'index'])->name('home');

// Formulář hlášení.
Route::get('/hlaseni', [HlaseniController::class, 'index'])->name('hlaseni.index');
Route::post('/hlaseni', [HlaseniController::class, 'store'])->middleware('throttle:hlaseni')->name('hlaseni.store');

// --- Veřejné ---
// Výpis kol je jen pro admina (route kola.admin.index); staré /kola přesměrujeme.
Route::redirect('/kola', '/admin/kola');

Route::get('/vysledky', [VysledkyController::class, 'listina'])->name('vysledkova_listina');
Route::get('/vysledky/pribezne', [VysledkyController::class, 'pribezne'])->name('pribezne_vysledky');
Route::get('/vysledky/rocni', [VysledkyController::class, 'rocni'])->name('rocni_vysledky');

// Obfuskovaný e-mail jako obrázek (footer) – nahrazuje mail.php
Route::get('/mail-image', [MailImageController::class, 'show'])->name('mail.image');

// Diskuse k závodnímu kolu
Route::get('/diskuse', [DiskuseController::class, 'index'])->name('diskuse.index');
Route::get('/diskuse/{kolo}', [DiskuseController::class, 'show'])->name('diskuse.show');
Route::post('/diskuse/{kolo}', [DiskuseController::class, 'store'])->middleware('throttle:diskuse')->name('diskuse.store');

// Nahrání EDI deníku (i ruční hlášení) řeší Livewire komponent App\Livewire\Prihlaska
// vykreslený na stránce /hlaseni – samostatná routa /edi pro upload už není potřeba.

// Zobrazení EDI deníku (sloupec „Akce / EDI" ve výsledkové listině):
//   EDI  – původní EDI soubor,
//   EDIR – redukovaný EDI (oříznutý na závodní okno 08:00–11:00 UTC).
Route::get('/edi/{head}/soubor', [EdiController::class, 'zobrazit'])->name('edi.soubor');
Route::get('/edi/{head}/soubor-redukovany', [EdiController::class, 'zobrazitRedukovany'])->name('edi.soubor.redukovany');

// Komplexní vizualizace deníku: mapy (5 přepínatelných vrstev: přehrávání,
// CRK, ježek, špendlíky, lokátory) + grafy a TOP ODX na jedné stránce
// (Leaflet + Chart.js). Nahrazuje dřívější samostatné mapové pohledy
// /edi/{head}/mapa/* (M/N/S/C) i zrušený Vizuální inkubátor.
Route::get('/edi/{head}/vizualizace', [EdiVizualizaceController::class, 'show'])->name('edi.vizualizace');

// Porovnání dvou deníků (hráč vs. hráč) z téhož kola a téže kategorie –
// mapa rozdílů v protistanicích + překryvný graf průběhu skóre.
Route::get('/edi/{head}/porovnani', [EdiPorovnaniController::class, 'show'])->name('edi.porovnani');

// --- Administrace (chráněno middleware z Fáze 4) ---
Route::middleware('admin')->group(function (): void {
    Route::get('/admin/statistiky', [DashboardController::class, 'index'])
        ->name('admin.dashboard');

    // Přehled a správa kol (admin index).
    Route::get('/admin/kola', [KolaAdminController::class, 'index'])->name('kola.admin.index');

    // CRUD kol – vytvoření, editace (název, data, aktivní příznak).
    Route::get('/admin/kola/create', [KolaAdminController::class, 'create'])->name('kola.admin.create');
    Route::post('/admin/kola', [KolaAdminController::class, 'store'])->name('kola.admin.store');
    Route::get('/admin/kola/{kolo}/edit', [KolaAdminController::class, 'edit'])->name('kola.admin.edit');
    Route::patch('/admin/kola/{kolo}', [KolaAdminController::class, 'update'])->name('kola.admin.update');

    // Vyhodnocení kola probíhá automaticky (denní příkaz kola:finalize-evaluated:
    // po uzávěrce, když jsou všechny záznamy převzaty nebo uplynulo 20 dní) –
    // žádná ruční akce „vyhodnotit/uzavřít" už není.

    // CRUD nad záznamem výsledkové listiny:
    //   P – převzít (PATCH), X – smazat (DELETE). U = editace přes GET hlaseni.index?id=
    Route::patch('/admin/zaznamy/{zaznam}', [ZaznamController::class, 'update'])->name('zaznam.update');
    Route::delete('/admin/zaznamy/{zaznam}', [ZaznamController::class, 'destroy'])->name('zaznam.destroy');

    Route::delete('/admin/diskuse/{prispevek}', [DiskuseController::class, 'destroy'])->name('diskuse.destroy');

    // EDI debug – nahrání deníku a rozpad bodování řádek po řádku (jen náhled).
    Route::get('/admin/edi-debug', [EdiDebugController::class, 'create'])->name('edi.debug.create');
    Route::post('/admin/edi-debug', [EdiDebugController::class, 'analyze'])->name('edi.debug.store');
    Route::get('/admin/edi-debug/{head}', [EdiDebugController::class, 'show'])->name('edi.debug.show')->whereNumber('head');

    Route::get('/admin/deniky', [DenikyController::class, 'index'])->name('deniky.index');

    // Export EDI deníků po kolech (ZIP archiv).
    Route::get('/admin/export', [ExportController::class, 'index'])->name('export.index');
    Route::get('/admin/export/{kolo}', [ExportController::class, 'download'])->name('export.download');

    Route::get('/admin/uzivatele', [UzivateleController::class, 'index'])->name('uzivatele.index');
    Route::get('/admin/kategorie', [KategorieController::class, 'index'])->name('kategorie.index');
    Route::get('/admin/kategorie/create', [KategorieController::class, 'create'])->name('kategorie.create');
    Route::post('/admin/kategorie', [KategorieController::class, 'store'])->name('kategorie.store');
    Route::get('/admin/kategorie/{kategorie}/edit', [KategorieController::class, 'edit'])->name('kategorie.edit');
    Route::patch('/admin/kategorie/{kategorie}', [KategorieController::class, 'update'])->name('kategorie.update');
    Route::get('/admin/importy', [ImportController::class, 'index'])->name('importy.index');
    Route::post('/admin/importy', [ImportController::class, 'store'])->name('importy.store');
});
