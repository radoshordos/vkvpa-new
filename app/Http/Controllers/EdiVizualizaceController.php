<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QsoMode;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\PorovnaniRivals;
use App\Services\Edi\PrefixResolver;
use App\Services\Edi\QsoGeometry;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Komplexní vizualizace deníku: mapa (vč. přehrávání deníku), grafy a TOP ODX
 * na jedné stránce. Geometrii spojení (souřadnice, vzdálenost, azimut, body,
 * čtverce) počítá sdílená {@see QsoGeometry}, agregace pro grafy
 * {@see DenikStatistiky}. Porovnání s deníkem soupeře je na stránce
 * Porovnání deníků ({@see EdiPorovnaniController}).
 */
class EdiVizualizaceController extends Controller
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly DenikStatistiky $statistiky,
        private readonly PorovnaniRivals $porovnani,
    ) {}

    public function show(EdiHead $head): View
    {
        // Vizualizace je veřejná vždy (zobrazuje jen vlastní deník účastníka);
        // citlivá vrstva roundStations se vydává až po uzavření kola
        // (viz QsoGeometry).
        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = Maidenhead::bigSquare((string) $head->p_wwlo);

        $enriched = $this->geometry->enrichedQsos($head, $home, 'qso_at');

        // Soapbox a poznámka účastníka žijí na záznamu hlášení (EdiEntry),
        // ne na hlavičce deníku – načteme je přes vazbu edi_head_id.
        $entry = EdiEntry::query()->where('edi_head_id', $head->id)->first();

        $fromMin = DenikStatistiky::minutes(ContestWindow::from());
        $toMin = DenikStatistiky::minutes(ContestWindow::to());

        $multiplier = $this->statistiky->noveNasobice($enriched, $homeSq);

        // Rozpad QSO podle zemí/prefixů (číselník prefixes); koš pro neznámé
        // značky lokalizovaně.
        $prefixy = PrefixResolver::fromDatabase();
        $ostatni = (string) __('pages.viz.country_other');

        return view('pages.vizualizace', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->p_call,
            'homeLoc' => (string) $head->p_wwlo,
            'homeSq' => $homeSq,
            'home' => $home,
            'ediSouborDostupny' => trim((string) $head->src) !== '',
            'soapbox' => $entry !== null ? $entry->soapbox : '',
            'poznamka' => $entry !== null ? $entry->note : '',
            'window' => ['from' => $fromMin, 'to' => $toMin],
            'mapPoints' => $enriched->map(fn (EnrichedQso $q): array => [
                'lat' => $q->lat,
                'lon' => $q->lon,
                'call' => $q->call,
                'wwl' => $q->wwl,
                'points' => $q->points,
                'dist' => $q->dist,
                'azimut' => $q->azimut,
                'mode' => $q->mode->value,
                'time' => $q->timeMinutes,
            ]),
            'multiplier' => $multiplier,
            'squares' => $this->geometry->bigSquares($head),
            'roundStations' => $this->geometry->roundStations($head),
            'roundDataPending' => ! $this->geometry->roundResultsDisclosable($head),
            'porovnaniDostupne' => $this->porovnani->hasRivals($head),
            'cumulative' => $this->geometry->prubehSkore($enriched, $homeSq),
            'timeline' => $this->statistiky->timeline($enriched, $multiplier, $fromMin, $toMin),
            'azimuth' => $this->statistiky->azimuthRose($enriched),
            'squarePoints' => $this->statistiky->bodyPodleCtvercu($enriched),
            'podleZemi' => $this->statistiky->podleZemi($enriched, $prefixy, $ostatni),
            'podlePrefixu' => $this->statistiky->podlePrefixu($enriched, $prefixy, $ostatni),
            'sezona' => $this->statistiky->sezona($head),
            'tempo' => $this->statistiky->tempo($enriched, $fromMin, $toMin),
            'modeStats' => $this->statistiky->modeStats($enriched),
            'odx' => $this->statistiky->topOdx($enriched),
            'nezapocitanaCelkem' => $this->statistiky->nezapocitana($head)['celkem'],
            'distHistogram' => $this->distHistogram($enriched),
            'stats' => $this->stats($enriched),
        ]);
    }

    /**
     * Histogram vzdáleností v km → počty QSO, rozdělený podle druhu provozu
     * (skládaný sloupcový graf: SSB / CW / ostatní).
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{labels: list<string>, ssb: list<int>, cw: list<int>, ostatni: list<int>}
     */
    private function distHistogram(Collection $lines): array
    {
        $labels = ['0–50', '50–100', '100–200', '200–400', '400–700', '700+'];
        $ssb = array_fill(0, 6, 0);
        $cw = array_fill(0, 6, 0);
        $ostatni = array_fill(0, 6, 0);

        foreach ($lines as $l) {
            if ($l->dist === null) {
                continue;
            }

            $d = $l->dist;
            $i = match (true) {
                $d < 50 => 0,
                $d < 100 => 1,
                $d < 200 => 2,
                $d < 400 => 3,
                $d < 700 => 4,
                default => 5,
            };

            match ($l->mode) {
                QsoMode::Ssb => $ssb[$i]++,
                QsoMode::Cw => $cw[$i]++,
                default => $ostatni[$i]++,
            };
        }

        return ['labels' => $labels, 'ssb' => $ssb, 'cw' => $cw, 'ostatni' => $ostatni];
    }

    /**
     * @param  Collection<int, EnrichedQso>  $lines
     * @return array{pocet: int, maxDist: int, avgDist: int, uniqueSq: int}
     */
    private function stats(Collection $lines): array
    {
        $dists = [];

        foreach ($lines as $l) {
            if ($l->dist !== null) {
                $dists[] = $l->dist;
            }
        }

        $maxDist = $dists !== [] ? max($dists) : 0;
        $avgDist = count($dists) > 0 ? (int) round(array_sum($dists) / count($dists)) : 0;

        $uniqueSq = $lines
            ->map(fn (EnrichedQso $l): string => Maidenhead::bigSquare($l->wwl))
            ->unique()
            ->count();

        return [
            'pocet' => $lines->count(),
            'maxDist' => $maxDist,
            'avgDist' => $avgDist,
            'uniqueSq' => $uniqueSq,
        ];
    }
}
