<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Výsledkové listiny (Fáze 7). Pořadí (`poradi`) počítá ScoringService
 * při vyhodnocení/uzávěrce; zde se jen zobrazuje seřazené.
 */
class VysledkyController extends Controller
{
    public function __construct(private readonly ScoringService $scoring)
    {
    }

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

        $radky = $kolo
            ? VkvpaData::query()
                ->where('id_kola', $kolo->id)
                ->when($jenPrevzate, fn ($q) => $q->where('schvaleno', true))
                ->when($request->boolean('qrp'), fn ($q) => $q->where('qrp', true))
                ->when($hledat !== '', fn ($q) => $q->where(
                    fn ($w) => $w->where('znacka', 'like', sprintf('%%%s%%', $hledat))
                        ->orWhere('locator', 'like', sprintf('%%%s%%', $hledat)),
                ))
                ->orderBy('id_kategorie')->orderBy('poradi')->orderByDesc('body')
                ->get()
            : collect();

        return view('pages.vysledky-listina', [
            'active' => 'vysledkova_listina',
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->get(),
            'kolo' => $kolo,
            'kategorie' => VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id'),
            'radky' => $radky,
            'hledat' => $hledat,
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
