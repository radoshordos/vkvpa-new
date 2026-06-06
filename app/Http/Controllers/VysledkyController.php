<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use App\Support\VkvpaSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Výsledkové listiny. Pořadí (`poradi`) počítá ScoringService při vyhodnocení/uzávěrce.
 */
class VysledkyController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

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

        $radky = $kolo
            ? VkvpaData::query()
                ->where('id_kola', $kolo->id)
                ->when($jenPrevzate, fn ($q) => $q->where('schvaleno', true))
                ->when($request->boolean('qrp'), fn ($q) => $q->where('qrp', true))
                ->when($hledat !== '', fn ($q) => $q->where(
                    fn ($w) => $w->where('znacka', 'like', sprintf('%%%s%%', $hledat))
                        ->orWhere('locator', 'like', sprintf('%%%s%%', $hledat)),
                ))
                ->with('kategorie')
                ->orderBy('id_kategorie')->orderBy('poradi')->orderByDesc('body')
                ->limit($maxRows)
                ->get()
            : collect();

        return view('pages.vysledky-listina', [
            'active' => 'vysledkova_listina',
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->get(),
            'kolo' => $kolo,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id'),
            'radky' => $radky,
            'hledat' => $hledat,
            'limitReached' => $radky->count() >= $maxRows,
        ]);
    }

    public function pribezne(Request $request): View
    {
        $aktivniKola = VkvpaKola::query()->active()->orderByDesc('datum_konani')->get();

        $koloId = $request->integer('kolo');
        $kolo = $koloId !== 0
            ? $aktivniKola->firstWhere('id', $koloId)
            : $aktivniKola->first();

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
            'kola' => $aktivniKola,
            'kolo' => $kolo,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->whereIn('id', $obsazeneKatIds)->get()->keyBy('id'),
            'katId' => $katId,
            'vysledky' => $vysledky,
        ]);
    }

    public function rocni(Request $request): View
    {
        $rok = $request->integer('rok', (int) date('Y'));
        $qrp = $request->boolean('qrp');

        $vysledky = $this->scoring->yearlyResults($rok, $qrp)
            ->groupBy('kategorie_id');

        return view('pages.vysledky-rocni', [
            'active' => 'rocni_vysledky',
            'rok' => $rok,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id'),
            'vysledky' => $vysledky,
        ]);
    }
}
