<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\EdiParseException;
use App\Models\VkvpaData;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Nahrání EDI deníku (sladěno s upload_processor.php + read_edin.php v6.70).
 *
 * Na rozdíl od legacy upload_processoru parsujeme i QSO řádky do `edilines`
 * (přes EdiParser/EdiImportService) – jen tak lze spočítat skóre. Poté se,
 * stejně jako v legacy, založí „rezervovaný" řádek ve vkvpa_data (schvaleno=0)
 * a jeho ID se uloží do session; formulář pak tento řádek edituje.
 */
class EdiController extends Controller
{
    public function __construct(
        private readonly EdiParser $parser,
        private readonly EdiImportService $importer,
        private readonly ScoringService $scoring,
    ) {
    }

    public function create(): View
    {
        return view('pages.edi-upload', ['active' => 'edit_hlaseni']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'upload' => ['required', 'file', 'max:500'],
        ]);

        $content = (string) file_get_contents($request->file('upload')->getRealPath());

        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $e) {
            return back()
                ->withErrors(['upload' => $e->getMessage()])
                ->with('lineErrors', $e->lineErrors);
        }

        $head = $this->importer->import($log);
        $score = $this->scoring->scoreEdi($head);
        $h = $log->header;

        // Rezervovaný řádek (schvaleno=0) – formulář ho převezme a doplní.
        $row = VkvpaData::create([
            'id_kola' => $this->scoring->koloForTDate($h->tDate()) ?? 0,
            'id_kategorie' => 0,
            'znacka' => $h->pCall(),
            'locator' => $h->pWWLo(),
            'jmeno' => $h->rName(),
            'mail' => $h->rHBBS() !== '' ? $h->rHBBS() : $h->get('REmai'),
            'telefon' => $h->rPhon(),
            'soapbox' => $h->get('RSoap'),
            'pocet' => $score->pocet,
            'nasobice' => $score->nasobice,
            'bodu_za_qso' => 0,
            'body' => $score->body,
            'qrp' => $h->isQrp(),
            'EDI' => true,
            'EDI_ID' => $head->ID,
            'schvaleno' => false,
        ]);

        // ID vlastněného řádku v session – povolí jeho editaci i nepřihlášenému.
        $request->session()->put('owned_data_id', $row->id);

        return redirect()->route('edit_hlaseni', ['import' => 'success']);
    }
}
