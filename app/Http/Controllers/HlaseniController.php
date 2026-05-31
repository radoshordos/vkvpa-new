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
 * Podání a správa hlášení (sladěno s edit_hlaseni.php v4.1.3).
 *
 * Stránka standardně ukazuje EDI upload box; ruční formulář se zobrazí
 * po EDI uploadu, při editaci (?id), po „vyplnit ručně" (?showfrm) nebo
 * po chybě validace. Pod tím průběžné výsledky vybraného kola.
 */
class HlaseniController extends Controller
{
    public function index(Request $request): View
    {
        $prefill = (array) session('edi_prefill', []);

        $editId = (int) $request->integer('id');
        $edit = $editId > 0 ? VkvpaData::find($editId) : null;

        // Ruční formulář: po EDI uploadu / editaci / „ručně" / po chybě validace.
        $showManual = $edit !== null
            || $prefill !== []
            || $request->has('showfrm')
            || old('znacka') !== null;

        // Kolo pro filtr průběžných výsledků.
        $idKola = $edit?->id_kola
            ?? ($prefill['kolo'] ?? null)
            ?? ($request->integer('kolo') ?: null);

        $vysledky = $idKola
            ? VkvpaData::query()
                ->where('id_kola', $idKola)
                ->where('schvaleno', true)
                ->orderBy('id_kategorie')->orderByDesc('body')
                ->get()
            : collect();

        return view('pages.hlaseni', [
            'active' => 'edit_hlaseni',
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->limit(36)->get(),
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get(),
            'showManual' => $showManual,
            'edit' => $edit,
            'idKola' => $idKola,
            'vysledky' => $vysledky,
        ]);
    }

    public function store(StoreHlaseniRequest $request): RedirectResponse
    {
        $v = $request->validated();
        $idZaznamu = (int) ($v['id_zaznamu'] ?? 0);

        // Editace existujícího záznamu jen pro administrátora (ochrana proti IDOR).
        if ($idZaznamu > 0 && ! ($request->user()?->is_admin)) {
            abort(403, 'Úpravu existujícího hlášení může provést jen administrátor.');
        }

        $payload = [
            'id_kola' => (int) $v['kolo'],
            'id_kategorie' => (int) ($v['kategorie'] ?? 0),
            'znacka' => $v['znacka'],
            'locator' => $v['locator'],
            'pocet' => (int) ($v['pocet'] ?? 0),
            'bodu_za_qso' => (int) ($v['bodu_za_qso'] ?? 0),
            'nasobice' => (int) ($v['nasobice'] ?? 0),
            'body' => (int) ($v['body'] ?? 0),
            'qrp' => (bool) ($v['qrp'] ?? false),
            'mail' => $v['email'],
            'jmeno' => $v['jmeno'] ?? '',
            'telefon' => $v['telefon'] ?? '',
            'soapbox' => $v['soapbox'] ?? '',
            'poznamka' => $v['poznamka'] ?? '',
            'EDI_ID' => (int) ($v['EDIID'] ?? 0),
            'schvaleno' => true,
        ];

        if ($idZaznamu > 0) {
            VkvpaData::findOrFail($idZaznamu)->update($payload);
        } else {
            VkvpaData::create($payload);
        }

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $payload['id_kola']])
            ->with('announcement', 'Hlášení bylo uloženo.');
    }
}
