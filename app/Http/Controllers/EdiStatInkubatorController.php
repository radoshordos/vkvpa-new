<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\QsoGeometry;
use App\Services\Edi\StatistikyInkubator;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\View\View;

/**
 * INKUBÁTOR statistik – hřiště s prototypy nových grafů a analýz nad jedním
 * deníkem ({@see StatistikyInkubator}). Stránka je veřejná stejně jako
 * Vizualizace; funkce závislé na denících soupeřů (vs. pole kategorie,
 * promarněné příležitosti, závod) se sestaví až po uzávěrce kola.
 *
 * Záměrně oddělené od produkční Vizualizace – odsud se osvědčené funkce
 * postupně přesunou do {@see EdiVizualizaceController}.
 */
final class EdiStatInkubatorController extends Controller
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly StatistikyInkubator $inkubator,
    ) {}

    public function show(Edihead $head): View
    {
        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = Maidenhead::bigSquare((string) $head->p_wwlo);

        $enriched = $this->geometry->enrichedQsos($head, $home, 'qso_at');

        $fromMin = DenikStatistiky::minutes(ContestWindow::from());
        $toMin = DenikStatistiky::minutes(ContestWindow::to());

        return view('pages.statistiky-inkubator', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->p_call,
            'homeLoc' => (string) $head->p_wwlo,
            'window' => ['from' => $fromMin, 'to' => $toMin],
            'heatmap' => $this->inkubator->heatmapaSmerCas($enriched, $fromMin, $toMin),
            'pole' => $this->inkubator->analyzaPole($head, $home, $homeSq, $fromMin, $toMin),
            'roundDataPending' => $head->round_id !== null && ! $this->geometry->roundResultsDisclosable($head),
            'qso' => $enriched->map(fn (EnrichedQso $q): array => [
                'call' => $q->call,
                'wwl' => $q->wwl,
                'time' => $q->timeMinutes,
                'dist' => $q->dist,
                'azimut' => $q->azimut,
                'points' => $q->points,
                'mode' => $q->mode->label(),
            ])->all(),
        ]);
    }
}
