<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Administrace – dashboard se statistikami sezóny. */
final class DashboardController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    public function index(): View
    {
        $rok = now()->year;

        $kolaIds = VkvpaKola::whereYear('datum_konani', $rok)->pluck('id');

        $celkemKol = VkvpaKola::count();
        $kolaTento = VkvpaKola::whereYear('datum_konani', $rok)->count();
        $celkemZnacek = VkvpaData::approved()->distinct()->count('znacka');
        $znackyTento = VkvpaData::approved()->whereIn('id_kola', $kolaIds)->distinct()->count('znacka');

        // Trend – posledních 12 kol s počtem schválených účastníků
        $trendKola = VkvpaKola::query()
            ->withCount(['hlaseni as pocet' => fn (Builder $q) => $q->where('schvaleno', true)])
            ->orderByDesc('datum_konani')
            ->limit(12)
            ->get(['id', 'nazev', 'datum_konani'])
            ->reverse()
            ->values();

        // Distribuce kategorií – schválené záznamy v tomto roce
        $kategorieData = VkvpaData::query()
            ->approved()
            ->whereIn('id_kola', $kolaIds)
            ->select('id_kategorie', DB::raw('count(*) as pocet'))
            ->groupBy('id_kategorie')
            ->get();

        $kategorie = VkvpaKategorie::query()->orderBy('id')->get()->keyBy('id');

        // Top 10 stanic roku (cached)
        $top10 = $this->scoring->yearlyResults($rok)->take(10);

        return view('pages.admin.dashboard', [
            'active' => 'admin.dashboard',
            'rok' => $rok,
            'celkemKol' => $celkemKol,
            'kolaTento' => $kolaTento,
            'celkemZnacek' => $celkemZnacek,
            'znackyTento' => $znackyTento,
            'trendKola' => $trendKola,
            'kategorieData' => $kategorieData,
            'kategorie' => $kategorie,
            'top10' => $top10,
        ]);
    }
}
