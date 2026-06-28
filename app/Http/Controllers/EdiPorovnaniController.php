<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\PorovnaniRivals;
use App\Services\Edi\QsoGeometry;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Porovnání dvou deníků (hráč vs. hráč) na samostatné stránce: mapa rozdílů
 * v protistanicích (jen já / jen soupeř / oba) a překryvný graf průběhu skóre.
 * Funkce sem byla přesunuta ze stránek „Vizualizace" a „Vizuální inkubátor".
 *
 * Výběr soupeřů (totéž kolo a kategorie, až po uzávěrce kola) řeší sdílená
 * {@see PorovnaniRivals}.
 *
 * @phpstan-import-type CompareStation from QsoGeometry
 */
class EdiPorovnaniController extends Controller
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly PorovnaniRivals $porovnani,
        private readonly DenikStatistiky $stats,
    ) {}

    public function show(Request $request, Edihead $head): View
    {
        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = Maidenhead::bigSquare((string) $head->p_wwlo);

        $fromMin = DenikStatistiky::minutes(ContestWindow::from());
        $toMin = DenikStatistiky::minutes(ContestWindow::to());

        $rivals = $this->porovnani->rivals($head);
        $rival = $rivals->firstWhere('id', $request->integer('porovnat'));

        $enriched = $this->geometry->enrichedQsos($head, $home, 'qso_at');
        $cumulative = $this->geometry->prubehSkore($enriched, $homeSq);

        $compare = null;
        $rivalCumulative = null;
        $timeline = null;
        $azimuth = null;

        if ($rival !== null) {
            $diff = $this->geometry->compareWith($head, $rival, $home);

            if ($diff !== null) {
                $rivalHome = Maidenhead::toLatLon((string) $rival->p_wwlo);

                $compare = [
                    'rivalId' => $rival->id,
                    'rival' => (string) $rival->p_call,
                    'rivalLoc' => (string) $rival->p_wwlo,
                    'rivalHome' => $rivalHome,
                    ...$diff,
                ];

                $rivalSq = Maidenhead::bigSquare((string) $rival->p_wwlo);
                $rivalEnriched = $this->geometry->enrichedQsos($rival, $rivalHome, 'qso_at');
                $rivalCumulative = $this->geometry->prubehSkore($rivalEnriched, $rivalSq);

                $timeline = [
                    'labels' => $this->stats->timelineLabels($fromMin, $toMin),
                    'mine' => $this->stats->timelineCounts($enriched, $fromMin, $toMin),
                    'rival' => $this->stats->timelineCounts($rivalEnriched, $fromMin, $toMin),
                ];

                $azimuth = [
                    'labels' => ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'],
                    'mine' => $this->stats->azimuthCounts($enriched),
                    'rival' => $this->stats->azimuthCounts($rivalEnriched),
                ];
            }
        }

        return view('pages.porovnani', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->p_call,
            'homeLoc' => (string) $head->p_wwlo,
            'home' => $home,
            'window' => ['from' => $fromMin, 'to' => $toMin],
            'rivals' => $rivals,
            'compare' => $compare,
            'cumulative' => $cumulative,
            'rivalCumulative' => $rivalCumulative,
            'timeline' => $timeline,
            'azimuth' => $azimuth,
            'souhrn' => $compare === null ? null : [
                'mine' => self::souhrn((string) $head->p_call, $cumulative),
                'rival' => self::souhrn($compare['rival'], $rivalCumulative),
            ],
            'roundDataPending' => $head->round_id !== null && ! $this->geometry->roundResultsDisclosable($head),
        ]);
    }

    /**
     * Souhrnná karta jedné strany porovnání z průběhu skóre (poslední bod
     * průběhu = výsledné hodnoty; jen QSO s platným lokátorem).
     *
     * @param  list<array{t: int, cas: string, call: string, points: int, multiplier: int, body: int}>  $cumulative
     * @return array{call: string, qso: int, multiplier: int, body: int}
     */
    private static function souhrn(string $call, array $cumulative): array
    {
        $last = $cumulative === [] ? null : $cumulative[count($cumulative) - 1];

        return [
            'call' => $call,
            'qso' => count($cumulative),
            'multiplier' => $last['multiplier'] ?? 0,
            'body' => $last['body'] ?? 0,
        ];
    }
}
