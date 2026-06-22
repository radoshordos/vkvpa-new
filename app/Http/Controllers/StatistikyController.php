<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KoloStav;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Edi\KoloStatistiky;
use App\Services\Scoring\RekordyService;
use App\Services\Scoring\StaniceProfil;
use Illuminate\View\View;

/**
 * Veřejné statistiky kol: rozcestník vyhodnocených kol (+ all-time síň slávy)
 * a detail jednoho kola (souhrn, mapy, grafy, TOP žebříčky, trend, zajímavosti).
 * Agregaci dodává {@see KoloStatistiky}, rekordy {@see RekordyService};
 * zveřejňují se jen vyhodnocená kola.
 */
final class StatistikyController extends Controller
{
    public function __construct(
        private readonly KoloStatistiky $statistiky,
        private readonly RekordyService $rekordy,
        private readonly StaniceProfil $staniceProfil,
    ) {}

    public function index(): View
    {
        // „Účastníci" = počet unikátních značek kola (shodně s detailem i síní
        // slávy); jedna značka může mít víc záznamů (kategorií), proto distinct.
        $kola = VkvpaKola::query()
            ->whereNotNull('vyhodnoceno')
            ->select(['id', 'nazev', 'datum_konani', 'vyhodnoceno'])
            ->selectSub(
                VkvpaData::query()
                    ->whereColumn('id_kola', 'vkvpa_kola.id')
                    ->where('schvaleno', true)
                    ->selectRaw('COUNT(DISTINCT znacka)'),
                'ucastniku',
            )
            ->orderByDesc('datum_konani')
            ->get();

        return view('pages.statistiky.index', [
            'active' => 'statistiky.index',
            'kola' => $kola,
            'rekordy' => $this->rekordy->vrcholy(),
            'odxAllTime' => $this->rekordy->odxAllTime(),
        ]);
    }

    public function kolo(VkvpaKola $kolo): View
    {
        // Veřejně jen vyhodnocená kola – u rozpracovaných by se zveřejňovala
        // neúplná, měnící se data (a deníky soupeřů během příjmu hlášení).
        abort_unless($kolo->stav() === KoloStav::Vyhodnocene, 404);

        return view('pages.statistiky.kolo', [
            'active' => 'statistiky.index',
            'kolo' => $kolo,
            'prehled' => $this->statistiky->prehled($kolo),
        ]);
    }

    public function stanice(string $znacka): View
    {
        $profil = $this->staniceProfil->profil($znacka);
        abort_if($profil === null, 404);

        return view('pages.statistiky.stanice', [
            'active' => 'statistiky.index',
            'profil' => $profil,
        ]);
    }
}
