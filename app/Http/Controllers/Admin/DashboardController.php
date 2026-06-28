<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
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

        $kolaIds = EdiRound::whereYear('starts_at', $rok)->pluck('id');

        $celkemKol = EdiRound::count();
        $kolaTento = $kolaIds->count();
        $celkemZnacek = EdiEntry::approved()->distinct()->count('callsign');
        $znackyTento = EdiEntry::approved()->whereIn('round_id', $kolaIds)->distinct()->count('callsign');

        // 1. Záznamy čekající na schválení
        $cekajici = EdiEntry::whereIn('round_id', $kolaIds)->where('approved', false)->count();

        // 3. Průměrné body a QSO pro rok – jeden SELECT místo dvou
        $avgRow = EdiEntry::approved()->whereIn('round_id', $kolaIds)
            ->toBase()
            ->selectRaw('AVG(body) as avg_body, AVG(pocet) as avg_pocet')
            ->first();
        $avgBody = (int) round(is_numeric($avgRow?->avg_body) ? (float) $avgRow->avg_body : 0);
        $avgQso = (int) round(is_numeric($avgRow?->avg_pocet) ? (float) $avgRow->avg_pocet : 0);

        // Trend – posledních 12 kol s počtem schválených účastníků
        $trendKola = EdiRound::query()
            ->withCount(['hlaseni as pocet' => fn (Builder $q) => $q->where('approved', true)])
            ->orderByDesc('starts_at')
            ->limit(12)
            ->get(['id', 'name', 'starts_at'])
            ->reverse()
            ->values();

        // 4. Graf rok vs. rok – schválení účastníci per kolo pro předchozí rok
        //    (aktuální rok se čte z $kolaRoku, viz níže – sdílíme jeden dotaz)
        $trendPredchoziRok = EdiRound::whereYear('starts_at', $rok - 1)
            ->withCount(['hlaseni as pocet' => fn (Builder $q) => $q->where('approved', true)])
            ->orderBy('starts_at')
            ->get(['id', 'name', 'starts_at']);

        // Distribuce kategorií – schválené záznamy v tomto roce
        $kategorieData = EdiEntry::query()
            ->approved()
            ->whereIn('round_id', $kolaIds)
            ->select('category_id', DB::raw('count(*) as pocet'))
            ->groupBy('category_id')
            ->get();

        $kategorie = EdiCategory::query()->orderBy('id')->get()->keyBy('id');

        // 2. Přehled kol roku – počty přihlášených, schválených a čekajících
        $kolaRoku = EdiRound::whereYear('starts_at', $rok)
            ->withCount([
                'hlaseni as pocet_celkem',
                'hlaseni as pocet_schvalenych' => fn (Builder $q) => $q->where('approved', true),
            ])
            ->orderBy('starts_at')
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
