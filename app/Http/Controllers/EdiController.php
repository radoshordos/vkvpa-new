<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\EdiParseException;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Nahrání EDI deníku (Fáze 6) – nahrazuje read_edi.php.
 * Parsování a zápis využívá služby z Fáze 5 (čistě oddělené).
 */
class EdiController extends Controller
{
    public function __construct(
        private readonly EdiParser $parser,
        private readonly EdiImportService $importer,
        private readonly \App\Services\Scoring\ScoringService $scoring,
    ) {
    }

    public function create(): View
    {
        return view('pages.edi-upload', ['active' => 'edit_hlaseni']);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'upload' => ['required', 'file', 'max:500'], // 500 kB jako legacy MAX_FILE_SIZE
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

        // Předvyplnění formuláře hlášení (skóre dopočítáno z deníku, Fáze 7).
        return redirect()->route('edit_hlaseni')->with('edi_prefill', [
            'znacka' => $log->header->pCall(),
            'lokator' => $log->header->pWWLo(),
            'jmeno' => $log->header->rName(),
            'mail' => $log->header->rHBBS(),
            'telefon' => $log->header->rPhon(),
            'qrp' => $log->header->isQrp(),
            'EDI' => true,
            'EDIID' => $head->ID,
            'pocet' => $score->platnych,
            'bodu_za_qso' => $score->lbody,
            'nasobice' => $score->lnasobic,
            'body' => $score->body(),
        ]);
    }
}
