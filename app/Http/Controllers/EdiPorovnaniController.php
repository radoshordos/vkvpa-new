<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Services\Edi\QsoGeometry;
use App\Support\ContestWindow;
use App\Support\Maidenhead;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Porovnání dvou deníků (hráč vs. hráč) na samostatné stránce: mapa rozdílů
 * v protistanicích (jen já / jen soupeř / oba) a překryvný graf průběhu skóre.
 * Funkce sem byla přesunuta ze stránek „Vizualizace" a „Vizuální inkubátor".
 *
 * Porovnat lze jen deníky z téhož kola a téže kategorie – soupeři se nabízejí
 * podle schválených záznamů výsledkové listiny ({@see VkvpaData}). Pravidlo
 * férovosti je shodné s vizualizací: soupeřův deník se vydá až po uzávěrce,
 * resp. vyhodnocení kola ({@see QsoGeometry::roundResultsDisclosable()}).
 *
 * @phpstan-import-type CompareStation from QsoGeometry
 */
class EdiPorovnaniController extends Controller
{
    public function __construct(private readonly QsoGeometry $geometry) {}

    public function show(Request $request, Edihead $head): View
    {
        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = strtoupper(substr((string) $head->p_wwlo, 0, 4));

        [$rivals, $rival] = $this->rivals($request, $head);

        $compare = null;
        $rivalCumulative = null;

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

                $rivalSq = strtoupper(substr((string) $rival->p_wwlo, 0, 4));
                $rivalCumulative = $this->geometry->prubehSkore(
                    $this->geometry->enrichedQsos($rival, $rivalHome, 'time'),
                    $rivalSq,
                );
            }
        }

        $cumulative = $this->geometry->prubehSkore(
            $this->geometry->enrichedQsos($head, $home, 'time'),
            $homeSq,
        );

        return view('pages.porovnani', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->p_call,
            'homeLoc' => (string) $head->p_wwlo,
            'home' => $home,
            'window' => [
                'from' => self::minutes(ContestWindow::from()),
                'to' => self::minutes(ContestWindow::to()),
            ],
            'rivals' => $rivals,
            'compare' => $compare,
            'cumulative' => $cumulative,
            'rivalCumulative' => $rivalCumulative,
            'souhrn' => $compare === null ? null : [
                'mine' => self::souhrn((string) $head->p_call, $cumulative),
                'rival' => self::souhrn($compare['rival'], $rivalCumulative),
            ],
            'roundDataPending' => $head->id_kola !== null && ! $this->geometry->roundResultsDisclosable($head),
        ]);
    }

    /**
     * Soupeři pro porovnání: deníky z téhož kola a téže kategorie (podle
     * schválených záznamů výsledkové listiny), seřazené podle značky. Druhý
     * prvek je zvolený soupeř (query parametr `porovnat`), pokud je v nabídce.
     *
     * Vydává se až po uzávěrce/vyhodnocení kola; bez kola nebo bez záznamu
     * s kategorií se nenabízí nic.
     *
     * @return array{0: EloquentCollection<int, Edihead>, 1: Edihead|null}
     */
    private function rivals(Request $request, Edihead $head): array
    {
        if ($head->id_kola === null || ! $this->geometry->roundResultsDisclosable($head)) {
            return [new EloquentCollection, null];
        }

        $entry = VkvpaData::query()
            ->approved()
            ->where('edihead_id', $head->id)
            ->first(['id_kategorie']);

        if ($entry === null || $entry->id_kategorie === null) {
            return [new EloquentCollection, null];
        }

        $rivalHeadIds = VkvpaData::query()
            ->approved()
            ->where('id_kola', $head->id_kola)
            ->where('id_kategorie', $entry->id_kategorie)
            ->whereNotNull('edihead_id')
            ->where('edihead_id', '!=', $head->id)
            ->pluck('edihead_id');

        $rivals = Edihead::query()
            ->whereIn('id', $rivalHeadIds)
            ->where('id_kola', $head->id_kola)
            ->orderBy('p_call')
            ->get();

        return [$rivals, $rivals->firstWhere('id', $request->integer('porovnat'))];
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

    /** Čas „HHMM" → minuty od půlnoci. */
    private static function minutes(string $hhmm): int
    {
        return (int) substr($hhmm, 0, 2) * 60 + (int) substr($hhmm, 2, 2);
    }
}
