<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RankRoundJob;
use App\Models\VkvpaData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Admin akce nad jedním záznamem výsledkové listiny (řádkem hlášení `vkvpa_data`).
 *
 * Pokrývá tlačítka ze sloupce „Akce / EDI":
 *   P – PŘEVZÍT / vrátit záznam (toggle převzetí)  → {@see update()}
 *   X – smazat záznam                             → {@see destroy()}
 * (U = úprava je řešena přes HlaseniController::index s ?id a HlaseniController::store.)
 *
 * „Převzetí" = vyhodnocovatel záznam zkontroloval; v DB je to `schvaleno = true`.
 * Nepřevzatý záznam (schvaleno = false) je ve výpisu podbarven meruňkově.
 *
 * Všechny akce vyžadují přihlášeného administrátora (middleware `admin`)
 * a jsou navázané přes route-model-binding na model {@see VkvpaData}.
 *
 * @api  Tyto endpointy budou později popsány v OpenAPI/Swagger – komentáře
 *       u metod proto drží jednotnou strukturu (Endpoint / Vstup / Efekt / Návrat).
 */
class ZaznamController extends Controller
{
    /**
     * Přepne převzetí záznamu – tlačítko „P" ve výsledkové listině (toggle).
     *
     * Endpoint: PATCH /admin/zaznamy/{zaznam}  (name: zaznam.update)
     * Vstup:    {zaznam} = id řádku vkvpa_data (route-model-binding)
     * Oprávnění: jen administrátor (middleware `admin`)
     * Efekt:    překlopí `schvaleno` (nepřevzatý ↔ převzatý) a přepočítá pořadí
     *           v kole, aby se změna promítla do žebříčku (do pořadí se počítají
     *           jen převzaté záznamy).
     * Návrat:   redirect zpět na výsledkovou listinu kola záznamu + hláška.
     */
    public function update(VkvpaData $zaznam): RedirectResponse
    {
        $idKola = $zaznam->id_kola;
        $znacka = $zaznam->znacka;

        $prevzato = ! $zaznam->schvaleno;
        $zaznam->update(['schvaleno' => $prevzato]);
        RankRoundJob::dispatch($idKola);

        Log::info($prevzato ? 'admin.zaznam.prevzit' : 'admin.zaznam.odebrat-prevzeti', [
            'zaznam_id' => $zaznam->id,
            'znacka' => $znacka,
            'kolo_id' => $idKola,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $idKola])
            ->with('announcement', $prevzato
                ? 'Záznam „'.$znacka.'" byl převzat.'
                : 'Záznam „'.$znacka.'" byl vrácen mezi nepřevzaté.');
    }

    /**
     * Smaže záznam – tlačítko „X" ve výsledkové listině.
     *
     * Endpoint: DELETE /admin/zaznamy/{zaznam}  (name: zaznam.destroy)
     * Vstup:    {zaznam} = id řádku vkvpa_data (route-model-binding)
     * Oprávnění: jen administrátor (middleware `admin`)
     * Efekt:    odstraní řádek hlášení (EDI deník v `edihead`/`edilines`
     *           zůstává; maže se jen výsledkový řádek) a přepočítá pořadí kola.
     * Návrat:   redirect zpět na výsledkovou listinu kola + hláška.
     */
    public function destroy(VkvpaData $zaznam): RedirectResponse
    {
        // Údaje potřebné po smazání si uložíme předem (po delete už nejsou k dispozici).
        $idKola = $zaznam->id_kola;
        $znacka = $zaznam->znacka;

        $zaznam->delete();
        RankRoundJob::dispatch($idKola);

        Log::info('admin.zaznam.smazat', [
            'zaznam_id' => $zaznam->id,
            'znacka' => $znacka,
            'kolo_id' => $idKola,
            'admin' => Auth::user()?->name,
        ]);

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $idKola])
            ->with('announcement', 'Záznam „'.$znacka.'" byl smazán.');
    }
}
