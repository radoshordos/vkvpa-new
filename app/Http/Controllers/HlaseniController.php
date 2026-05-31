<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreHlaseniRequest;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Podání a správa hlášení (sladěno s edit_hlaseni.php v4.1.3 a tokem
 * upload → rezervovaný řádek → editace formulářem).
 */
class HlaseniController extends Controller
{
    public function index(Request $request): View
    {
        $ownedId = $this->intFrom($request->session()->get('owned_data_id', 0));
        $editId = $request->integer('id'); // editace adminem přes ?id

        $targetId = $editId ?: $ownedId;
        $edit = $targetId > 0 ? VkvpaData::find($targetId) : null;

        $showManual = $edit !== null
            || $request->has('showfrm')
            || old('znacka') !== null;

        $idKola = $edit->id_kola ?? ($request->integer('kolo') ?: null);
        $idKategorie = $edit->id_kategorie ?? ($request->integer('kategorie') ?: null);

        // Průběžné výsledky vybraného kola (i nezveřejněné = stav „Čeká").
        $vysledky = $idKola
            ? VkvpaData::query()
                ->where('id_kola', $idKola)
                ->when($idKategorie, fn ($q) => $q->where('id_kategorie', $idKategorie))
                ->orderBy('id_kategorie')
                ->orderByDesc('body')->orderByDesc('pocet')
                ->get()
            : collect();

        return view('pages.hlaseni', [
            'active' => 'edit_hlaseni',
            'maAktivniKolo' => VkvpaKola::existujeAktivni() || (bool) ($request->user()?->is_admin),
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
        $ownedId = $this->intFrom($request->session()->get('owned_data_id', 0));

        // Hlášení lze odeslat jen do aktivního kola (admin má výjimku).
        if (! ($request->user()?->is_admin) && ! VkvpaKola::jeAktivni($this->intFrom($v['kolo']))) {
            return back()->withInput()->withErrors([
                'kolo' => 'Do tohoto kola nelze odeslat hlášení – není aktivní. / Period is not active.',
            ]);
        }

        // Editovat existující smí admin, nebo autor čerstvého importu (vlastní řádek v session).
        if ($idZaznamu > 0 && ! ($request->user()?->is_admin) && $idZaznamu !== $ownedId) {
            abort(403, 'Úpravu tohoto hlášení může provést jen administrátor.');
        }

        $payload = [
            'id_kola' => $this->intFrom($v['kolo']),
            'id_kategorie' => $this->intFrom($v['kategorie'] ?? 0),
            'znacka' => $v['znacka'],
            'locator' => $v['locator'],
            'pocet' => $this->intFrom($v['pocet'] ?? 0),
            'bodu_za_qso' => $this->intFrom($v['bodu_za_qso'] ?? 0),
            'nasobice' => $this->intFrom($v['nasobice'] ?? 0),
            'body' => $this->intFrom($v['body'] ?? 0),
            'qrp' => (bool) ($v['qrp'] ?? false),
            'mail' => $v['email'],
            'jmeno' => $v['jmeno'] ?? '',
            'telefon' => $v['telefon'] ?? '',
            'soapbox' => $v['soapbox'] ?? '',
            'poznamka' => $v['poznamka'] ?? '',
            'EDI_ID' => $this->intFrom($v['EDIID'] ?? 0),
            'schvaleno' => true,
        ];

        if ($idZaznamu > 0) {
            VkvpaData::findOrFail($idZaznamu)->update($payload);
        } else {
            VkvpaData::create($payload);
        }

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
}
