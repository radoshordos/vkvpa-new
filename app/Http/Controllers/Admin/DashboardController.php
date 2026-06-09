<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Administrace – dashboard se statistikami sezóny. */
final class DashboardController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    public function index(Request $request): View
    {
        $rok = max(2000, min((int) $request->query('rok', now()->year), now()->year));

        $kolaIds = VkvpaKola::whereYear('datum_konani', $rok)->pluck('id');

        $celkemKol = VkvpaKola::count();
        $kolaTento = $kolaIds->count();
        $celkemZnacek = VkvpaData::approved()->distinct()->count('znacka');
        $znackyTento = VkvpaData::approved()->whereIn('id_kola', $kolaIds)->distinct()->count('znacka');

        // 1. Záznamy čekající na schválení
        $cekajici = VkvpaData::whereIn('id_kola', $kolaIds)->where('schvaleno', false)->count();

        // 3. Průměrné body a QSO pro rok – jeden SELECT místo dvou
        $avgRow = VkvpaData::approved()->whereIn('id_kola', $kolaIds)
            ->toBase()
            ->selectRaw('AVG(body) as avg_body, AVG(pocet) as avg_pocet')
            ->first();
        $avgBody = (int) round(is_numeric($avgRow?->avg_body) ? (float) $avgRow->avg_body : 0);
        $avgQso = (int) round(is_numeric($avgRow?->avg_pocet) ? (float) $avgRow->avg_pocet : 0);

        // Trend – posledních 12 kol s počtem schválených účastníků
        $trendKola = VkvpaKola::query()
            ->withCount(['hlaseni as pocet' => fn (Builder $q) => $q->where('schvaleno', true)])
            ->orderByDesc('datum_konani')
            ->limit(12)
            ->get(['id', 'nazev', 'datum_konani'])
            ->reverse()
            ->values();

        // 4. Graf rok vs. rok – schválení účastníci per kolo pro předchozí rok
        //    (aktuální rok se čte z $kolaRoku, viz níže – sdílíme jeden dotaz)
        $trendPredchoziRok = VkvpaKola::whereYear('datum_konani', $rok - 1)
            ->withCount(['hlaseni as pocet' => fn (Builder $q) => $q->where('schvaleno', true)])
            ->orderBy('datum_konani')
            ->get(['id', 'nazev', 'datum_konani']);

        // Distribuce kategorií – schválené záznamy v tomto roce
        $kategorieData = VkvpaData::query()
            ->approved()
            ->whereIn('id_kola', $kolaIds)
            ->select('id_kategorie', DB::raw('count(*) as pocet'))
            ->groupBy('id_kategorie')
            ->get();

        $kategorie = VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id');

        // 2. Přehled kol roku – počty přihlášených, schválených a čekajících
        $kolaRoku = VkvpaKola::whereYear('datum_konani', $rok)
            ->withCount([
                'hlaseni as pocet_celkem',
                'hlaseni as pocet_schvalenych' => fn (Builder $q) => $q->where('schvaleno', true),
            ])
            ->orderBy('datum_konani')
            ->get();

        // Top 10 stanic roku (cached)
        $top10 = $this->scoring->yearlyResults($rok)->take(10);

        return view('pages.admin.dashboard', [
            'active' => 'admin.dashboard',
            'rok' => $rok,
            'celkemKol' => $celkemKol,
            'kolaTento' => $kolaTento,
            'celkemZnacek' => $celkemZnacek,
            'znackyTento' => $znackyTento,
            'cekajici' => $cekajici,
            'avgBody' => $avgBody,
            'avgQso' => $avgQso,
            'trendKola' => $trendKola,
            'trendPredchoziRok' => $trendPredchoziRok,
            'kategorieData' => $kategorieData,
            'kategorie' => $kategorie,
            'kolaRoku' => $kolaRoku,
            'top10' => $top10,
        ]);
    }
}
