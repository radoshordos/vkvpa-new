<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RankRoundJob;
use App\Models\EdiEntry;
use App\Services\Scoring\ScoringService;
use App\Support\AdminLogger;
use Illuminate\Http\RedirectResponse;

/**
 * Admin akce nad jedním záznamem výsledkové listiny (řádkem hlášení `edi_entries`).
 *
 * Pokrývá tlačítka ze sloupce „Akce / EDI":
 *   P – PŘEVZÍT / vrátit záznam (toggle převzetí)  → {@see update()}
 *   X – smazat záznam                             → {@see destroy()}
 * (U = úprava je řešena přes HlaseniController::index s ?id a HlaseniController::store.)
 *
 * „Převzetí" = vyhodnocovatel záznam zkontroloval; v DB je to `approved = true`.
 * Nepřevzatý záznam (approved = false) je ve výpisu podbarven meruňkově.
 *
 * Všechny akce vyžadují přihlášeného administrátora (middleware `admin`)
 * a jsou navázané přes route-model-binding na model {@see EdiEntry}.
 *
 * @api  Tyto endpointy budou později popsány v OpenAPI/Swagger – komentáře
 *       u metod proto drží jednotnou strukturu (Endpoint / Vstup / Efekt / Návrat).
 */
class ZaznamController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    /**
     * Přepne převzetí záznamu – tlačítko „P" ve výsledkové listině (toggle).
     *
     * Endpoint: PATCH /admin/zaznamy/{zaznam}  (name: zaznam.update)
     * Vstup:    {zaznam} = id řádku edi_entries (route-model-binding)
     * Oprávnění: jen administrátor (middleware `admin`)
     * Efekt:    překlopí `approved` (nepřevzatý ↔ převzatý) a přepočítá pořadí
     *           v kole, aby se změna promítla do žebříčku (do pořadí se počítají
     *           jen převzaté záznamy). Dvě omezení životního cyklu kola:
     *             - odebrat převzetí (převzatý → nepřevzatý) lze jen mezi startem
     *               závodu a uzávěrkou (stavy Probíhá/Příjem); po uzávěrce už
     *               záznam nelze vrátit, jen upravit,
     *             - převzetí posledního dosud nepřevzatého záznamu po uzávěrce
     *               kolo rovnou vyhodnotí (nastaví `vyhodnoceno`).
     * Návrat:   redirect zpět na výsledkovou listinu kola záznamu + hláška.
     */
    public function update(EdiEntry $zaznam): RedirectResponse
    {
        $kolo = $zaznam->round;
        $idKola = $zaznam->round_id;
        $znacka = $zaznam->callsign;
        $prevzato = ! $zaznam->approved;

        // Vrátit převzetí lze jen mezi starts_at a closes_at.
        if (! $prevzato && ! ($kolo?->acceptsReports() ?? false)) {
            return redirect()
                ->route('vysledkova_listina', ['kolo' => $idKola])
                ->with('announcement', 'Po uzávěrce už nelze vrátit převzetí záznamu „'.$znacka.'" – lze ho pouze upravit.');
        }

        $zaznam->update(['approved' => $prevzato]);

        // Převzetí posledního záznamu po uzávěrce kolo rovnou vyhodnotí (přepočítá
        // pořadí + nastaví vyhodnoceno); jinak stačí přepočet pořadí.
        $vyhodnoceno = $prevzato && $kolo !== null && $this->scoring->finalizeIfDue($kolo);
        if (! $vyhodnoceno) {
            RankRoundJob::dispatchSync($idKola);
        }

        AdminLogger::log($prevzato ? 'admin.zaznam.prevzit' : 'admin.zaznam.odebrat-prevzeti', [
            'zaznam_id' => $zaznam->id,
            'znacka' => $znacka,
            'round_id' => $idKola,
            'vyhodnoceno' => $vyhodnoceno,
        ]);

        $zprava = match (true) {
            $vyhodnoceno => 'Záznam „'.$znacka.'" převzat – všechny záznamy převzaty, kolo bylo vyhodnoceno.',
            $prevzato => 'Záznam „'.$znacka.'" byl převzat.',
            default => 'Záznam „'.$znacka.'" byl vrácen mezi nepřevzaté.',
        };

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $idKola])
            ->with('announcement', $zprava);
    }

    /**
     * Smaže záznam – tlačítko „X" ve výsledkové listině.
     *
     * Endpoint: DELETE /admin/zaznamy/{zaznam}  (name: zaznam.destroy)
     * Vstup:    {zaznam} = id řádku edi_entries (route-model-binding)
     * Oprávnění: jen administrátor (middleware `admin`)
     * Efekt:    odstraní řádek hlášení (EDI deník v `edihead`/`edilines`
     *           zůstává; maže se jen výsledkový řádek) a přepočítá pořadí kola.
     * Návrat:   redirect zpět na výsledkovou listinu kola + hláška.
     */
    public function destroy(EdiEntry $zaznam): RedirectResponse
    {
        // Údaje potřebné po smazání si uložíme předem (po delete už nejsou k dispozici).
        $idKola = $zaznam->round_id;
        $znacka = $zaznam->callsign;

        $zaznam->delete();
        RankRoundJob::dispatchSync($idKola);

        AdminLogger::log('admin.zaznam.smazat', [
            'zaznam_id' => $zaznam->id,
            'znacka' => $znacka,
            'round_id' => $idKola,
        ]);

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $idKola])
            ->with('announcement', 'Záznam „'.$znacka.'" byl smazán.');
    }
}
