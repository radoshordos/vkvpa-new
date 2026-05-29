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
        $koloId = (int) $request->integer('kolo');
        $kolo = $koloId
            ? VkvpaKola::find($koloId)
            : VkvpaKola::query()->where('datum_uzaverky', '<', now())->orderByDesc('datum_konani')->first();

        $radky = $kolo
            ? VkvpaData::query()
                ->where('id_kola', $kolo->id)
                ->where('schvaleno', true)
                ->when($request->boolean('qrp'), fn ($q) => $q->where('qrp', true))
                ->orderBy('id_kategorie')->orderBy('poradi')->orderByDesc('body')
                ->get()
            : collect();

        return view('pages.vysledky-listina', [
            'active' => 'vysledkova_listina',
            'kola' => VkvpaKola::query()->orderByDesc('datum_konani')->get(),
            'kolo' => $kolo,
            'radky' => $radky,
        ]);
    }

    public function rocni(Request $request): View
    {
        $rok = (int) $request->integer('rok', (int) date('Y'));
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
