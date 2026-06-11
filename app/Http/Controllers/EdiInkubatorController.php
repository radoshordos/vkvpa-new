<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Edihead;
use App\Models\VkvpaKola;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\PorovnaniRivals;
use App\Services\Edi\QsoGeometry;
use App\Support\Maidenhead;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Vizuální inkubátor – doplňkové tabulky deníku: nové násobiče
 * a nezapočítaná QSO. Grafy, mapa s přehráváním i TOP ODX se přestěhovaly
 * na stránku „Vizualizace" ({@see EdiVizualizaceController}); porovnání
 * s deníkem soupeře žije na samostatné stránce ({@see EdiPorovnaniController}).
 *
 * Geometrii spojení počítá sdílená {@see QsoGeometry}, agregace
 * {@see DenikStatistiky}.
 */
class EdiInkubatorController extends Controller
{
    public function __construct(
        private readonly QsoGeometry $geometry,
        private readonly DenikStatistiky $statistiky,
        private readonly PorovnaniRivals $porovnani,
    ) {}

    public function show(Edihead $head): View|RedirectResponse
    {
        if (! auth()->user()?->is_admin) {
            if (VkvpaKola::existujeAktivni()) {
                abort(403);
            }
            if (! auth()->check()) {
                return redirect()->route('login');
            }
        }

        $home = Maidenhead::toLatLon((string) $head->p_wwlo);
        $homeSq = strtoupper(substr((string) $head->p_wwlo, 0, 4));

        $enriched = $this->geometry->enrichedQsos($head, $home, 'time');

        return view('pages.inkubator', [
            'active' => '',
            'head' => $head,
            'pcall' => (string) $head->p_call,
            'homeLoc' => (string) $head->p_wwlo,
            'homeSq' => $homeSq,
            'nasobice' => $this->statistiky->noveNasobice($enriched, $homeSq),
            'nezapocitana' => $this->statistiky->nezapocitana($head),
            'porovnaniDostupne' => $this->porovnani->hasRivals($head),
        ]);
    }
}
