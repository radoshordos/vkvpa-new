<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaData;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;

/**
 * Admin akce nad jedním záznamem výsledkové listiny (řádkem hlášení `vkvpa_data`).
 *
 * Pokrývá tlačítka ze sloupce „Akce / EDI":
 *   P – PŘEVZÍT záznam (vyhodnocovatel ho viděl)  → {@see prevzit()}
 *   X – smazat záznam                             → {@see smazat()}
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
    public function __construct(private readonly ScoringService $scoring)
    {
    }

    /**
     * Převezme záznam – tlačítko „P" ve výsledkové listině.
     *
     * Endpoint: POST /admin/zaznam/{zaznam}/prevzit  (name: zaznam.prevzit)
     * Vstup:    {zaznam} = id řádku vkvpa_data (route-model-binding)
     * Oprávnění: jen administrátor (middleware `admin`)
     * Efekt:    nastaví `schvaleno = true` (záznam přestane být meruňkový)
     *           a přepočítá pořadí v kole, aby se promítl do žebříčku.
     * Návrat:   redirect zpět na výsledkovou listinu kola záznamu + hláška.
     */
    public function prevzit(VkvpaData $zaznam): RedirectResponse
    {
        $zaznam->update(['schvaleno' => true]);

        // Přepočet pořadí kola – množina převzatých (zveřejněných) záznamů se změnila.
        $this->scoring->rankRound($zaznam->id_kola);

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $zaznam->id_kola])
            ->with('announcement', 'Záznam „' . $zaznam->znacka . '" byl převzat.');
    }

    /**
     * Smaže záznam – tlačítko „X" ve výsledkové listině.
     *
     * Endpoint: POST /admin/zaznam/{zaznam}/smazat  (name: zaznam.smazat)
     * Vstup:    {zaznam} = id řádku vkvpa_data (route-model-binding)
     * Oprávnění: jen administrátor (middleware `admin`)
     * Efekt:    odstraní řádek hlášení (EDI deník v `edihead`/`edilines`
     *           zůstává; maže se jen výsledkový řádek) a přepočítá pořadí kola.
     * Návrat:   redirect zpět na výsledkovou listinu kola + hláška.
     */
    public function smazat(VkvpaData $zaznam): RedirectResponse
    {
        // Údaje potřebné po smazání si uložíme předem (po delete už nejsou k dispozici).
        $idKola = $zaznam->id_kola;
        $znacka = $zaznam->znacka;

        $zaznam->delete();

        // Přepočet pořadí kola – ze žebříčku zmizel jeden záznam.
        $this->scoring->rankRound($idKola);

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $idKola])
            ->with('announcement', 'Záznam „' . $znacka . '" byl smazán.');
    }
}
