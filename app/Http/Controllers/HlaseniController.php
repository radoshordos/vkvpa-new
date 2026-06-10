<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreHlaseniRequest;
use App\Jobs\RankRoundJob;
use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Podání a správa hlášení (tok: upload → rezervovaný řádek → editace formulářem).
 */
class HlaseniController extends Controller
{
    public function index(Request $request): View
    {
        $ownedId = $this->intFrom($request->session()->get('owned_data_id', 0));
        // Editace cizího záznamu přes ?id je povolena jen adminovi; běžný uživatel
        // smí pracovat výhradně s vlastním rezervovaným řádkem ze session.
        // Bez tohoto omezení by ?id=N vystavilo PII (e-mail, telefon) kohokoli.
        $editId = $request->user()?->is_admin ? $request->integer('id') : 0;

        $targetId = $editId ?: $ownedId;
        $edit = $targetId > 0 ? VkvpaData::find($targetId) : null;

        $showManual = $edit !== null
            || $request->has('showfrm')
            || old('znacka') !== null;

        $idKola = $edit->id_kola ?? ($request->integer('kolo') ?: null);
        $idKategorie = $edit->id_kategorie ?? ($request->integer('kategorie') ?: null);

        // Průběžné výsledky vybraného kola (i nezveřejněné = stav „Čeká").
        $vysledky = $idKola
            ? VkvpaData::prubezne($idKola, $idKategorie)->get()
            : collect();

        return view('pages.hlaseni', [
            'active' => 'edit_hlaseni',
            // Stránka hlášení (EDI i ruční formulář) jen v otevřeném upload okně;
            // admin ji vidí vždy (opravy starých kol). Vlastní rozpracovaný řádek
            // ze session smí účastník dokončit i po zavření okna.
            'maAktivniKolo' => VkvpaKola::existujeUploadOkno()
                || $edit !== null
                || (bool) ($request->user()?->is_admin),
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->limit(36)->get(),
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get(),
            'showManual' => $showManual,
            'edit' => $edit,
            'vysledky' => $vysledky,
        ]);
    }

    public function store(StoreHlaseniRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $idZaznamu = $this->intFrom($v['id_zaznamu'] ?? 0);
        $znacka = is_string($v['znacka'] ?? null) ? $v['znacka'] : '';

        // Hlášení (i manuální) lze odeslat jen v otevřeném upload okně kola.
        // Admin smí ukládat kdykoliv (opravy a doplňování starých kol) a vlastní
        // rezervovaný řádek (EDI nahraný na poslední chvíli) lze dokončit
        // i těsně po zavření okna – vlastnictví hlídá StoreHlaseniRequest.
        if ($idZaznamu === 0 && ! (bool) ($request->user()?->is_admin)) {
            $kolo = VkvpaKola::find($this->intFrom($v['kolo']));

            if ($kolo === null || ! $kolo->prijimaHlaseni()) {
                return back()
                    ->withErrors(['kolo' => 'Kolo právě nepřijímá hlášení – odeslat je lze jen v otevřeném upload okně (od dne závodu 08:00 UTC do uzávěrky).'])
                    ->withInput();
            }
        }

        $payload = [
            'id_kola' => $this->intFrom($v['kolo']),
            'id_kategorie' => isset($v['kategorie']) && is_numeric($v['kategorie']) ? (int) $v['kategorie'] : null,
            'znacka' => $znacka,
            'locator' => $v['locator'],
            'pocet' => $this->intFrom($v['pocet'] ?? 0),
            'bodu_za_qso' => $this->intFrom($v['bodu_za_qso'] ?? 0),
            'nasobice' => $this->intFrom($v['nasobice'] ?? 0),
            'body' => $this->intFrom($v['body'] ?? 0),
            'qrp' => (bool) ($v['qrp'] ?? false),
            'lp' => (bool) ($v['lp'] ?? false),
            'mail' => $v['email'],
            'jmeno' => $v['jmeno'] ?? '',
            'telefon' => $v['telefon'] ?? '',
            'soapbox' => $v['soapbox'] ?? '',
            'poznamka' => $v['poznamka'] ?? '',
            'edihead_id' => $this->ediheadIdFrom($v['edihead_id'] ?? 0),
            // Jen administrátor smí záznam rovnou „převzít". Hlášení od veřejnosti
            // zůstává ve stavu „Čeká" (schvaleno=false), dokud ho vyhodnocovatel
            // nepřevezme – brání to podvržení veřejně zobrazených výsledků.
            'schvaleno' => (bool) ($request->user()?->is_admin),
        ];

        if ($idZaznamu > 0) {
            $zaznam = VkvpaData::findOrFail($idZaznamu);
            $puvodniKolo = $zaznam->id_kola;
            $zaznam->update($payload);

            // Přesun záznamu do jiného kola → přepočítat i původní kolo.
            if ($puvodniKolo !== $payload['id_kola']) {
                RankRoundJob::dispatchSync($puvodniKolo);
            }
        } else {
            if (VkvpaData::query()->where('id_kola', $payload['id_kola'])->where('znacka', $znacka)->exists()) {
                return back()
                    ->withErrors(['znacka' => 'Pro toto kolo již existuje hlášení pro značku '.$znacka.'.'])
                    ->withInput();
            }

            VkvpaData::create($payload);
        }

        // Admin editace mění body/převzetí už zařazeného záznamu – pořadí kola
        // a cache ročních výsledků se musí přepočítat hned. U veřejného hlášení
        // (schvaleno=false, poradi=0) je přepočet neškodný.
        RankRoundJob::dispatchSync($payload['id_kola']);

        $request->session()->forget('owned_data_id');

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $payload['id_kola']])
            ->with('announcement', 'Hlášení bylo uloženo.');
    }

    /** Bezpečný převod nedůvěryhodné (mixed) vstupní hodnoty na int. */
    private function intFrom(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Vazba na EDI deník z formuláře (hidden pole, 0 = bez deníku). Neexistující
     * id se zahodí – sloupec má cizí klíč a podvržená hodnota by skončila 500.
     */
    private function ediheadIdFrom(mixed $value): ?int
    {
        $id = $this->intFrom($value);

        return $id > 0 && Edihead::query()->whereKey($id)->exists() ? $id : null;
    }
}
