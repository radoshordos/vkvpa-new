<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\EdiParseException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EdiController;
use App\Models\Edihead;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\EdiScoreDebugger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin nástroj „EDI debug" – nahrání EDI deníku a rozpad bodování řádek po
 * řádku. Pouze náhled: nic se neukládá do databáze (na rozdíl od běžného
 * uploadu v {@see EdiController}).
 */
class EdiDebugController extends Controller
{
    public function __construct(
        private readonly EdiParser $parser,
        private readonly EdiScoreDebugger $debugger,
    ) {}

    /** Zobrazí prázdný formulář pro nahrání EDI. */
    public function create(): View
    {
        return view('pages.admin.edi-debug', [
            'active'   => 'edit_edi_debug',
            'report'   => null,
            'filename' => null,
            'edihead'  => null,
        ]);
    }

    /** Naparsuje nahraný EDI deník a vykreslí debug rozpad bodování. */
    public function analyze(Request $request): View|RedirectResponse
    {
        $request->validate([
            'upload' => ['required', 'file', 'max:500'],
        ]);

        $content = (string) file_get_contents($request->file('upload')->getRealPath());

        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $ediParseException) {
            return back()
                ->withErrors(['upload' => $ediParseException->getMessage()])
                ->with('lineErrors', $ediParseException->lineErrors);
        }

        $edihead = Edihead::where('PCall', $log->header->pCall())
            ->where('TDate', $log->header->tDate())
            ->latest('ID')
            ->first();

        return view('pages.admin.edi-debug', [
            'active'   => 'edit_edi_debug',
            'report'   => $this->debugger->analyze($log),
            'filename' => $request->file('upload')->getClientOriginalName(),
            'edihead'  => $edihead,
        ]);
    }
}
