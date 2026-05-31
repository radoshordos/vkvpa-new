<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\EdiParseException;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Nahrání EDI deníku (sladěno s upload_processor.php / edit_hlaseni.php v4.1.3).
 * Parsuje, uloží edihead/edilines, spočítá skóre a předvyplní ruční formulář.
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

        return redirect()->route('edit_hlaseni')->with('edi_prefill', [
            'EDIID' => $head->ID,
            'EDI' => 1,
            'kolo' => $this->scoring->koloForTDate($log->header->tDate()),
            'znacka' => $log->header->pCall(),
            'locator' => $log->header->pWWLo(),
            'jmeno' => $log->header->rName(),
            'email' => $log->header->rHBBS(),
            'telefon' => $log->header->rPhon(),
            'qrp' => $log->header->isQrp(),
            'pocet' => $score->pocet,
            'nasobice' => $score->nasobice,
            'body' => $score->body,
        ]);
    }
}
