<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreHlaseniRequest;
use App\Mail\HlaseniPrijato;
use App\Mail\HlaseniProVyhodnocovatele;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Models\VkvpaPrihlaseni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Podání a správa hlášení (Fáze 6) – jádro z edit_hlaseni.php.
 *
 * Nahrazuje: extract($_POST), poziční INSERT a SQL injection
 * pojmenovaným zápisem přes Eloquent. Maily → Fáze 8. Plný formulář → 6c.
 */
class HlaseniController extends Controller
{
    public function index(Request $request): View
    {
        $kolo = $this->aktualniKolo();

        return view('pages.hlaseni', [
            'active' => 'edit_hlaseni',
            'kolo' => $kolo,
            // Seznam kol pro výběr (až 3 roky zpět), nejnovější první.
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->limit(36)->get(),
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get(),
            'hlaseni' => $kolo
                ? VkvpaData::query()->where('id_kola', $kolo->id)->orderByDesc('body')->get()
                : collect(),
        ]);
    }

    public function store(StoreHlaseniRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $hlaseni = VkvpaData::create([
            'id_kola' => (int) $data['kolo'],
            'id_kategorie' => (int) $data['kategorie'],
            'qrp' => (bool) ($data['qrp'] ?? false),
            'znacka' => $data['znacka'],
            'locator' => $data['lokator'],
            'pocet' => (int) $data['pocet'],
            'bodu_za_qso' => (int) $data['bodu_za_qso'],
            'nasobice' => (int) $data['nasobice'],
            'body' => (int) $data['body'],
            'jmeno' => $data['jmeno'] ?? '',
            'mail' => $data['mail'] ?? '',
            'telefon' => $data['telefon'] ?? '',
            'poznamka' => mb_substr((string) ($data['poznamka'] ?? ''), 0, 30),
            'soapbox' => nl2br(mb_substr((string) ($data['soapbox'] ?? ''), 0, 2000)),
            'ip' => (string) $request->ip(),
            'EDI' => (bool) ($data['EDI'] ?? false),
            'EDI_ID' => (int) ($data['EDIID'] ?? 0),
            'schvaleno' => false,
        ]);

        $this->odesliMaily($hlaseni);

        return redirect()
            ->route('edit_hlaseni')
            ->with('announcement', 'Hlášení bylo odesláno.');
    }

    /**
     * Potvrzovací e-mail účastníkovi + oznámení vyhodnocovateli s login kódem.
     * Nahrazuje mymail()/PHPMailer z edit_hlaseni.php (Fáze 8).
     */
    private function odesliMaily(VkvpaData $hlaseni): void
    {
        $koloNazev = (string) ($hlaseni->kolo?->nazev ?? '');
        $kategorieNazev = (string) ($hlaseni->kategorie?->nazev ?? '');

        // Jednorázový kód pro „převzít záznam" (login.token z Fáze 4).
        $kod = Str::random(40);
        VkvpaPrihlaseni::create(['kod' => $kod, 'time' => now()]);

        if ($hlaseni->mail !== '') {
            Mail::to($hlaseni->mail)->send(
                new HlaseniPrijato($hlaseni, $koloNazev, $kategorieNazev)
            );
        }

        Mail::to(config('vkvpa.contact_mail'))->send(
            new HlaseniProVyhodnocovatele($hlaseni, $koloNazev, $kategorieNazev, $kod)
        );
    }

    // --- Administrace (Fáze 6b) ---

    public function edit(VkvpaData $data): View
    {
        return view('pages.hlaseni', [
            'active' => 'edit_hlaseni',
            'kolo' => $data->kolo,
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->limit(36)->get(),
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get(),
            'hlaseni' => collect(),
            'edit' => $data,
        ]);
    }

    public function update(StoreHlaseniRequest $request, VkvpaData $data): RedirectResponse
    {
        $v = $request->validated();
        $data->update([
            'id_kola' => (int) $v['kolo'],
            'id_kategorie' => (int) $v['kategorie'],
            'znacka' => $v['znacka'],
            'locator' => $v['lokator'],
            'pocet' => (int) $v['pocet'],
            'bodu_za_qso' => (int) $v['bodu_za_qso'],
            'nasobice' => (int) $v['nasobice'],
            'body' => (int) $v['body'],
        ]);

        return redirect()->route('edit_hlaseni')->with('announcement', 'Hlášení upraveno.');
    }

    public function destroy(VkvpaData $data): RedirectResponse
    {
        $data->delete();

        return redirect()->route('edit_hlaseni')->with('announcement', 'Hlášení smazáno.');
    }

    private function aktualniKolo(): ?VkvpaKola
    {
        return VkvpaKola::query()
            ->whereDate('datum_konani', '<=', now())
            ->orderByDesc('datum_konani')
            ->first();
    }
}
