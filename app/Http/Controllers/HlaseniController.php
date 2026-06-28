<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreHlaseniRequest;
use App\Jobs\RankRoundJob;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\EdiRound;
use App\Services\Admin\AdminEntryChecker;
use Illuminate\Database\Eloquent\Builder;
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
        $edit = $targetId > 0 ? EdiEntry::find($targetId) : null;

        $idKola = $edit->round_id ?? ($request->integer('kolo') ?: null);
        $idKategorie = $edit->category_id ?? ($request->integer('kategorie') ?: null);

        // Průběžné výsledky vybraného kola (i nezveřejněné = stav „Čeká").
        $vysledky = $idKola
            ? EdiEntry::standings($idKola, $idKategorie)->get()
            : collect();

        $adminWarnings = ($request->user()?->is_admin && $edit !== null)
            ? app(AdminEntryChecker::class)->warnings($edit)
            : [];

        return view('pages.hlaseni', [
            'active' => 'edit_hlaseni',
            // Stránka hlášení (EDI i ruční formulář) jen v otevřeném upload okně;
            // admin ji vidí vždy (opravy starých kol). Vlastní rozpracovaný řádek
            // ze session smí účastník dokončit i po zavření okna.
            'maAktivniKolo' => EdiRound::uploadWindowExists()
                || $edit !== null
                || (bool) ($request->user()?->is_admin),
            // Do selektoru jen kola, která už začala – přesun záznamu do dosud
            // nezačatého (nadcházejícího) kola by ho schoval ve výsledkové listině,
            // která se otevírá až od startu závodu, a admin by se k němu (smazání,
            // editaci) už nedostal. Vlastní kolo editovaného záznamu se přidá vždy,
            // i kdyby bylo nadcházející (aby z formuláře nezmizelo).
            'kola' => EdiRound::query()
                ->where(function (Builder $q) use ($edit): void {
                    $q->where('starts_at', '<=', now());
                    if ($edit !== null) {
                        $q->orWhere('id', $edit->round_id);
                    }
                })
                ->orderByDesc('starts_at')
                ->limit(36)
                ->get(),
            'kategorie' => EdiCategory::query()->orderBy('id')->get(),
            'edit' => $edit,
            'vysledky' => $vysledky,
            'adminWarnings' => $adminWarnings,
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
            $kolo = EdiRound::find($this->intFrom($v['kolo']));

            if ($kolo === null || ! $kolo->acceptsReports()) {
                return back()
                    ->withErrors(['kolo' => 'Kolo právě nepřijímá hlášení – odeslat je lze jen v otevřeném upload okně (od dne závodu 08:00 UTC do uzávěrky).'])
                    ->withInput();
            }
        }

        $payload = [
            'round_id' => $this->intFrom($v['kolo']),
            'category_id' => isset($v['kategorie']) && is_numeric($v['kategorie']) ? (int) $v['kategorie'] : null,
            'callsign' => $znacka,
            'locator' => $v['locator'],
            'qso_count' => $this->intFrom($v['pocet'] ?? 0),
            'qso_points' => $this->intFrom($v['qso_points'] ?? 0),
            'multiplier' => $this->intFrom($v['multiplier'] ?? 0),
            'points' => $this->intFrom($v['body'] ?? 0),
            'qrp' => (bool) ($v['qrp'] ?? false),
            'lp' => (bool) ($v['lp'] ?? false),
            'email' => $v['email'] ?? '',
            'name' => $v['jmeno'] ?? '',
            'phone' => $v['telefon'] ?? '',
            'soapbox' => $v['soapbox'] ?? '',
            'note' => $v['poznamka'] ?? '',
            'edi_head_id' => $this->ediheadIdFrom($v['edihead_id'] ?? 0),
            // Jen administrátor smí záznam rovnou „převzít". Hlášení od veřejnosti
            // zůstává ve stavu „Čeká" (approved=false), dokud ho vyhodnocovatel
            // nepřevezme – brání to podvržení veřejně zobrazených výsledků.
            'approved' => (bool) ($request->user()?->is_admin),
        ];

        if ($idZaznamu > 0) {
            $zaznam = EdiEntry::findOrFail($idZaznamu);
            $puvodniKolo = $zaznam->round_id;
            $zaznam->update($payload);

            // Přesun záznamu do jiného kola → přepočítat i původní kolo.
            if ($puvodniKolo !== $payload['round_id']) {
                RankRoundJob::dispatchSync($puvodniKolo);
            }
        } else {
            if (EdiEntry::query()
                ->where('round_id', $payload['round_id'])
                ->where('callsign', $znacka)
                ->where('category_id', $payload['category_id'])
                ->exists()
            ) {
                return back()
                    ->withErrors(['znacka' => 'Pro toto kolo a kategorii již existuje hlášení pro značku '.$znacka.'.'])
                    ->withInput();
            }

            EdiEntry::create($payload);
        }

        // Admin editace mění body/převzetí už zařazeného záznamu – pořadí kola
        // a cache ročních výsledků se musí přepočítat hned. U veřejného hlášení
        // (approved=false, poradi=0) je přepočet neškodný.
        RankRoundJob::dispatchSync($payload['round_id']);

        $request->session()->forget('owned_data_id');

        return redirect()
            ->route('vysledkova_listina', ['kolo' => $payload['round_id']])
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
