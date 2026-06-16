<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\PorovnaniRivals;
use App\Services\Edi\QsoGeometry;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
    ) {}

    public function show(Request $request, Edihead $head): View
    {
        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = Maidenhead::bigSquare((string) $head->p_wwlo);

        $fromMin = DenikStatistiky::minutes(ContestWindow::from());
        $toMin = DenikStatistiky::minutes(ContestWindow::to());

        $rivals = $this->porovnani->rivals($head);
        $rival = $rivals->firstWhere('id', $request->integer('porovnat'));

        $enriched = $this->geometry->enrichedQsos($head, $home, 'time');
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
                $rivalEnriched = $this->geometry->enrichedQsos($rival, $rivalHome, 'time');
                $rivalCumulative = $this->geometry->prubehSkore($rivalEnriched, $rivalSq);

                $timeline = [
                    'labels' => self::timelineLabels($fromMin, $toMin),
                    'mine' => self::timelineCounts($enriched, $fromMin, $toMin),
                    'rival' => self::timelineCounts($rivalEnriched, $fromMin, $toMin),
                ];

                $azimuth = [
                    'labels' => ['S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ'],
                    'mine' => self::azimuthCounts($enriched),
                    'rival' => self::azimuthCounts($rivalEnriched),
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
            'roundDataPending' => $head->id_kola !== null && ! $this->geometry->roundResultsDisclosable($head),
        ]);
    }

    /**
     * Popisky 15minutových intervalů závodního okna („08:00", „08:15", …).
     *
     * @return list<string>
     */
    private static function timelineLabels(int $fromMin, int $toMin): array
    {
        $labels = [];

        for ($min = $fromMin; $min < $toMin; $min += 15) {
            $labels[] = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        }

        return $labels;
    }

    /**
     * Počty QSO v 15minutových intervalech závodního okna (QSO přesně na
     * konci okna patří do posledního intervalu) – shodné dělení jako
     * timeline v inkubátoru.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return list<int>
     */
    private static function timelineCounts(Collection $lines, int $fromMin, int $toMin): array
    {
        $count = max(1, (int) ceil(($toMin - $fromMin) / 15));
        $buckets = array_fill(0, $count, 0);

        foreach ($lines as $l) {
            $i = $l->timeMinutes === $toMin ? $count - 1 : intdiv($l->timeMinutes - $fromMin, 15);

            if ($i >= 0 && $i < $count) {
                $buckets[$i]++;
            }
        }

        return array_values($buckets);
    }

    /**
     * Počty QSO v 8 směrových sektorech (45°) po směru hodinových ručiček
     * od severu – pro směrovou růžici porovnání.
     *
     * @param  Collection<int, EnrichedQso>  $lines
     * @return list<int>
     */
    private static function azimuthCounts(Collection $lines): array
    {
        $counts = array_fill(0, 8, 0);

        foreach ($lines as $l) {
            if ($l->azimut === null) {
                continue;
            }
            $counts[(int) (($l->azimut + 22.5) / 45) % 8]++;
        }

        return array_values($counts);
    }

    /**
     * Souhrnná karta jedné strany porovnání z průběhu skóre (poslední bod
     * průběhu = výsledné hodnoty; jen QSO s platným lokátorem).
     *
     * @param  list<array{t: int, cas: string, call: string, points: int, nasobice: int, body: int}>  $cumulative
     * @return array{call: string, qso: int, nasobice: int, body: int}
     */
    private static function souhrn(string $call, array $cumulative): array
    {
        $last = $cumulative === [] ? null : $cumulative[count($cumulative) - 1];

        return [
            'call' => $call,
            'qso' => count($cumulative),
            'nasobice' => $last['nasobice'] ?? 0,
            'body' => $last['body'] ?? 0,
        ];
    }

}
