<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\EdiParseException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\EdiController;
use App\Http\Requests\Admin\EdiDebugUploadRequest;
use App\Models\EdiHead;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use App\Services\Scoring\EdiScoreDebugger;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
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
        private readonly ScoringService $scoring,
    ) {}

    /** Den závodu „YYMMDD" dle data konání kola (TDate); null = fallback na TDate. */
    private function contestDay(EdiLog $log): ?string
    {
        return $this->scoring->contestDay(null, $log->header->tDate())?->format('ymd');
    }

    /** Zobrazí prázdný formulář pro nahrání EDI. */
    public function create(): View
    {
        return view('pages.admin.edi-debug', [
            'active' => 'edit_edi_debug',
            'report' => null,
            'filename' => null,
            'edihead' => null,
            'zDatabaze' => false,
        ]);
    }

    /** Rozpad bodování deníku uloženého v databázi (sloupec `src`). */
    public function show(EdiHead $head): View|RedirectResponse
    {
        $src = (string) $head->src;

        if ($src === '') {
            return redirect()
                ->route('edi.debug.create')
                ->withErrors(['upload' => __('admin.debug_no_src', ['id' => $head->id])]);
        }

        try {
            $log = $this->parser->parse($src);
        } catch (EdiParseException $ediParseException) {
            return redirect()
                ->route('edi.debug.create')
                ->withErrors(['upload' => $ediParseException->getMessage()])
                ->with('lineErrors', $ediParseException->lineErrors);
        }

        return view('pages.admin.edi-debug', [
            'active' => 'edit_edi_debug',
            'report' => $this->debugger->analyze($log, $this->contestDay($log)),
            'filename' => $head->p_call,
            'edihead' => $head,
            'zDatabaze' => true,
        ]);
    }

    /** Naparsuje nahraný EDI deník a vykreslí debug rozpad bodování. */
    public function analyze(EdiDebugUploadRequest $request): View|RedirectResponse
    {
        $content = (string) file_get_contents($request->file('upload')->getRealPath());

        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $ediParseException) {
            return back()
                ->withErrors(['upload' => $ediParseException->getMessage()])
                ->with('lineErrors', $ediParseException->lineErrors);
        }

        $edihead = EdiHead::where('p_call', $log->header->pCall())
            ->where('t_date', $log->header->tDate())
            ->latest('id')
            ->first();

        return view('pages.admin.edi-debug', [
            'active' => 'edit_edi_debug',
            'report' => $this->debugger->analyze($log, $this->contestDay($log)),
            'filename' => preg_replace('/[^A-Za-z0-9._\-]/', '_', basename((string) $request->file('upload')->getClientOriginalName())),
            'edihead' => $edihead,
            'zDatabaze' => false,
        ]);
    }
}
