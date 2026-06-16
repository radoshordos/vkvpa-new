<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KoloStav;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use App\Services\Scoring\SkokanService;
use App\Support\VkvpaSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Výsledkové listiny. Pořadí (`poradi`) počítá ScoringService při vyhodnocení/uzávěrce.
 */
class VysledkyController extends Controller
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly SkokanService $skokan,
    ) {}

    public function listina(Request $request): View
    {
        $koloId = $request->integer('kolo');
        $kolo = $koloId !== 0
            ? VkvpaKola::find($koloId)
            : VkvpaKola::query()->where('datum_uzaverky', '<', now())->orderByDesc('datum_konani')->first();

        // Hledat / Search – filtruje podle značky nebo lokátoru ve vybraném kole.
        $hledat = $request->string('hledat')->trim()->value();

        // Veřejnost vidí jen převzaté (schvaleno=1); admin vidí i nepřevzaté
        // (meruňkové) záznamy, aby je mohl tlačítkem „P" převzít.
        $jenPrevzate = ! (bool) ($request->user()?->is_admin);

        $maxRows = VkvpaSettings::listaMaxRows();

        $radky = collect();
        $skokani = [];
        if ($kolo) {
            $radky = VkvpaData::query()
                ->where('id_kola', $kolo->id)
                ->when($jenPrevzate, fn ($q) => $q->where('schvaleno', true))
                ->when($request->boolean('qrp'), fn ($q) => $q->onlyQrp())
                ->when($request->boolean('lp'), fn ($q) => $q->onlyLp())
                ->when($hledat !== '', fn ($q) => $q->where(
                    fn ($w) => $w->where('znacka', 'like', sprintf('%%%s%%', $hledat))
                        ->orWhere('locator', 'like', sprintf('%%%s%%', $hledat)),
                ))
                ->with('kategorie')
                ->orderBy('id_kategorie')->orderBy('poradi')->orderByDesc('body')
                ->limit($maxRows)
                ->get();

            $skokani = $this->skokan->bodyDeltas($kolo, $radky);
        }

        return view('pages.vysledky-listina', [
            'active' => 'vysledkova_listina',
            // Nadcházející kola a kola bez jediného záznamu nemají co
            // zobrazit – ve výběru se nenabízejí.
            'kola' => VkvpaKola::query()->whereHas('hlaseni')->orderByDesc('datum_konani')->get()
                ->reject(fn (VkvpaKola $k): bool => $k->stav() === KoloStav::Nadchazejici)
                ->values(),
            'kolo' => $kolo,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id'),
            'radky' => $radky,
            'skokani' => $skokani,
            'hledat' => $hledat,
            'limitReached' => $radky->count() >= $maxRows,
            'uploadWindowOpen' => VkvpaKola::existujeUploadOkno(),
        ]);
    }

    public function pribezne(Request $request): View
    {
        $isAdmin = (bool) $request->user()?->is_admin;

        // Veřejnost: vždy jen jedno kolo – nejstarší nevyhodnocené s otevřeným
        // upload oknem; výběr kola se nenabízí. Admin smí listovat ve všech
        // kolech, která mají záznamy (výběr přes ?kolo=).
        $kolaVyber = $isAdmin
            ? VkvpaKola::query()->whereHas('hlaseni')->orderByDesc('datum_konani')->get()
            : collect();

        $kolo = VkvpaKola::aktualniProPrubezne();
        if ($isAdmin) {
            $zvolene = $request->integer('kolo');
            $kolo = $zvolene !== 0
                ? VkvpaKola::find($zvolene)
                // Bez výběru: aktuální průběžné, jinak nejnovější kolo se záznamy.
                : ($kolo ?? VkvpaKola::query()->whereHas('hlaseni')->orderByDesc('datum_konani')->first());
        }

        $katId = $request->integer('kategorie');

        // Průběžné výsledky vybraného kola – stejná data jako spodní část
        // stránky „Načíst EDI soubor" (i nepřevzaté = stav „Čeká").
        $vysledky = $kolo
            ? VkvpaData::prubezne($kolo->id, $katId ?: null)->get()
            : collect();

        $obsazeneKatIds = $kolo
            ? VkvpaData::query()->where('id_kola', $kolo->id)->distinct()->pluck('id_kategorie')
            : collect();

        return view('pages.pribezne-vysledky', [
            'active' => 'pribezne_vysledky',
            'kolo' => $kolo,
            'kolaVyber' => $kolaVyber,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->whereIn('id', $obsazeneKatIds)->get()->keyBy('id'),
            'katId' => $katId,
            'vysledky' => $vysledky,
        ]);
    }

    public function rocni(Request $request): View
    {
        $currentYear = (int) date('Y');
        $rok = max(2000, min($currentYear, $request->integer('rok', $currentYear)));
        $qrp = $request->boolean('qrp');
        $lp = $request->boolean('lp');
        $katId = $request->integer('kategorie');

        // Volitelný filtr zúží výpis na jedinou kategorii (filtrujeme řádky před
        // seskupením – Eloquent\Collection::only() bere klíče jako PK modelů).
        // Kategorie řadíme podle id (jinak by pořadí sekcí určovalo první výskyt
        // v seřazení podle bodů).
        $vysledky = $this->scoring->yearlyResults($rok, $qrp, $lp)
            ->when($katId !== 0, fn ($c) => $c->where('kategorie_id', $katId))
            ->groupBy('kategorie_id')
            ->sortKeys();

        return view('pages.vysledky-rocni', [
            'active' => 'rocni_vysledky',
            'rok' => $rok,
            'katId' => $katId,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id'),
            'vysledky' => $vysledky,
        ]);
    }
}
