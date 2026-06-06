<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DenikyController;
use App\Http\Controllers\Admin\EdiDebugController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\KategorieController;
use App\Http\Controllers\Admin\VyhodnoceniController;
use App\Http\Controllers\Admin\ZaznamController;
use App\Http\Controllers\DiskuseController;
use App\Http\Controllers\EdiController;
use App\Http\Controllers\EdiVizualizaceController;
use App\Http\Controllers\HlaseniController;
use App\Http\Controllers\KolaController;
use App\Http\Controllers\MailImageController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\VysledkyController;
use Illuminate\Support\Facades\Route;

/*
 * Routy aplikace (Fáze 6) – nahrazují index.php ?str= whitelist.
 * Legacy klíč ?str=X  →  pojmenovaná routa (name odpovídá klíči).
 */

require __DIR__.'/auth.php'; // Fáze 4

// Přepínání jazyka (cs / en) – ukládá volbu do session.
Route::get('/lang/{locale}', function (string $locale) {
    if (in_array($locale, ['cs', 'en'], true)) {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('lang.switch');

// Výchozí stránka = formulář hlášení.
Route::get('/', [HlaseniController::class, 'index'])->name('hlaseni.index');
Route::post('/hlaseni', [HlaseniController::class, 'store'])->name('hlaseni.store');

// --- Veřejné ---
Route::get('/kola', [KolaController::class, 'index'])->name('kola.index');

Route::get('/vysledky', [VysledkyController::class, 'listina'])->name('vysledkova_listina');
Route::get('/vysledky/pribezne', [VysledkyController::class, 'pribezne'])->name('pribezne_vysledky');
Route::get('/vysledky/rocni', [VysledkyController::class, 'rocni'])->name('rocni_vysledky');

// Obfuskovaný e-mail jako obrázek (footer) – nahrazuje mail.php
Route::get('/mail-image', [MailImageController::class, 'show'])->name('mail.image');

// Diskuse k závodnímu kolu
Route::get('/diskuse', [DiskuseController::class, 'index'])->name('diskuse.index');
Route::get('/diskuse/{kolo}', [DiskuseController::class, 'show'])->name('diskuse.show');
Route::post('/diskuse/{kolo}', [DiskuseController::class, 'store'])->middleware('throttle:5,1')->name('diskuse.store');

// Nahrání EDI deníku (využívá EdiParser/EdiImportService z Fáze 5).
Route::get('/edi', [EdiController::class, 'create'])->name('edi.create');
Route::post('/edi', [EdiController::class, 'store'])->middleware('throttle:10,1')->name('edi.store');

// Zobrazení EDI deníku (sloupec „Akce / EDI" ve výsledkové listině):
//   EDI  – původní EDI soubor,
//   EDIR – redukovaný EDI (oříznutý na závodní okno 08:00–11:00 UTC).
Route::get('/edi/{head}/soubor', [EdiController::class, 'zobrazit'])->name('edi.soubor');
Route::get('/edi/{head}/soubor-redukovany', [EdiController::class, 'zobrazitRedukovany'])->name('edi.soubor.redukovany');

// Mapové pohledy na spojení stanice (sloupec „Akce / EDI" ve výsledkové listině):
//   M – ježek (čáry do protistanic), N – špendlíky (značka/km/azimut),
//   S – velké čtverce (lokátory) s počtem protistanic.
Route::get('/edi/{head}/mapa/jezek', [MapController::class, 'jezek'])->name('edi.mapa.jezek');
Route::get('/edi/{head}/mapa/spendliky', [MapController::class, 'spendliky'])->name('edi.mapa.spendliky');
Route::get('/edi/{head}/mapa/lokatory', [MapController::class, 'lokatory'])->name('edi.mapa.lokatory');

// Technologické demo: všechny vizualizace na jedné stránce (Leaflet + Chart.js).
Route::get('/edi/{head}/vizualizace', [EdiVizualizaceController::class, 'show'])->name('edi.vizualizace');

// --- Administrace (chráněno middleware z Fáze 4) ---
Route::middleware('admin')->group(function (): void {
    // Vyhodnocení a uzávěrka kola
    Route::post('/admin/kola/{kolo}/vyhodnotit', [VyhodnoceniController::class, 'vyhodnotit'])->name('kola.vyhodnotit');
    Route::post('/admin/kola/{kolo}/uzavrit', [VyhodnoceniController::class, 'uzavrit'])->name('kola.uzavrit');

    // CRUD nad záznamem výsledkové listiny:
    //   P – převzít (PATCH), X – smazat (DELETE). U = editace přes GET hlaseni.index?id=
    Route::patch('/admin/zaznamy/{zaznam}', [ZaznamController::class, 'update'])->name('zaznam.update');
    Route::delete('/admin/zaznamy/{zaznam}', [ZaznamController::class, 'destroy'])->name('zaznam.destroy');

    Route::delete('/admin/diskuse/{prispevek}', [DiskuseController::class, 'destroy'])->name('diskuse.destroy');

    // EDI debug – nahrání deníku a rozpad bodování řádek po řádku (jen náhled).
    Route::get('/admin/edi-debug', [EdiDebugController::class, 'create'])->name('edi.debug.create');
    Route::post('/admin/edi-debug', [EdiDebugController::class, 'analyze'])->name('edi.debug.store');

    Route::get('/admin/deniky', [DenikyController::class, 'index'])->name('deniky.index');
    Route::get('/admin/kategorie', [KategorieController::class, 'index'])->name('kategorie.index');
    Route::post('/admin/kategorie', [KategorieController::class, 'store'])->name('kategorie.store');
    Route::get('/admin/importy', [ImportController::class, 'index'])->name('importy.index');
    Route::post('/admin/importy', [ImportController::class, 'store'])->name('importy.store');
});
