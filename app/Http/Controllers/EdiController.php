<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Exceptions\UnknownSectionException;
use App\Exceptions\UploadWindowClosedException;
use App\Http\Requests\StoreEdiRequest;
use App\Models\Edihead;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiReducer;
use App\Services\Edi\EdiValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Nahrání EDI deníku. QSO řádky se parsují do `edilines` (přes EdiParser/EdiImportService),
 * poté se založí „rezervovaný" řádek ve vkvpa_data (schvaleno=0) a jeho ID se uloží
 * do session; formulář pak tento řádek edituje.
 */
class EdiController extends Controller
{
    public function __construct(
        private readonly EdiParser $parser,
        private readonly ImportEdiAction $action,
        private readonly EdiReducer $reducer,
        private readonly EdiValidator $validator,
    ) {}

    public function create(): View
    {
        return view('pages.edi-upload', ['active' => 'edit_hlaseni']);
    }

    public function store(StoreEdiRequest $request): RedirectResponse
    {
        $content = (string) file_get_contents($request->file('upload')->getRealPath());

        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $e) {
            return back()
                ->withErrors(['upload' => $e->getMessage()])
                ->with('lineErrors', $e->lineErrors);
        }

        try {
            // Admin smí importovat deník i mimo upload okno (opravy starých kol).
            $row = $this->action->execute($log, enforceUploadWindow: ! (bool) $request->user()?->is_admin);
        } catch (TDateNotContestDayException|RoundNotFoundException|TDateMismatchException|DuplicateEdiException|UnknownBandException|UnknownSectionException|UploadWindowClosedException $e) {
            return back()->withErrors(['upload' => $e->getMessage()]);
        }

        // ID vlastněného řádku v session – povolí jeho editaci i nepřihlášenému.
        $request->session()->put('owned_data_id', $row->id);

        // Nefatální kontrola kvality deníku – upozornění se ukáže na hlášení.
        $warnings = $this->validator->validate($log)->messages();

        return redirect()
            ->route('hlaseni.index', ['import' => 'success'])
            ->with('importWarnings', $warnings);
    }

    /**
     * Zobrazí původní EDI soubor deníku – akce „EDI" ve výsledkové listině.
     *
     * Přístup:
     *   – admin: vždy povolen,
     *   – otevřené upload window (aktivní kolo): 403 Omezeno,
     *   – jinak (žádné aktivní kolo): povolen i nepřihlášeným.
     */
    public function zobrazit(Edihead $head): Response
    {
        $this->assertEdiAccess();

        return $this->ediResponse($head, (string) $head->src, $head->p_call, 'edi');
    }

    /**
     * Zobrazí REDUKOVANÝ EDI soubor (oříznutý na závodní okno 08:00–11:00 UTC).
     * Stejná přístupová pravidla jako {@see zobrazit()}.
     */
    public function zobrazitRedukovany(Edihead $head): Response
    {
        $this->assertEdiAccess();

        $src = (string) $head->src;
        $reduced = $src === '' ? '' : $this->reducer->reduce($src);

        return $this->ediResponse($head, $reduced, $head->p_call, 'edir');
    }

    /**
     * Admin má vždy přístup. Ostatní (včetně nepřihlášených) jsou blokováni
     * (403) jen v době otevřeného upload window, aby během příjmu hlášení
     * neunikaly deníky soupeřů.
     */
    private function assertEdiAccess(): void
    {
        if (! auth()->user()?->is_admin && VkvpaKola::existujeUploadOkno()) {
            abort(403);
        }
    }

    private function ediResponse(Edihead $head, string $content, string $pcall, string $variant): Response
    {
        if (trim($content) === '') {
            abort(404, 'EDI soubor není pro tento deník k dispozici.');
        }

        $base = $pcall !== '' ? $pcall : 'denik';
        // Sanitize: keep only safe ASCII chars to prevent header injection.
        $safe = preg_replace('/[^A-Za-z0-9\-]/', '_', $base) ?? 'denik';
        $filename = sprintf('%s-%d-%s.edi', $safe, $head->id, $variant);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"; filename*=UTF-8''{$filename}",
        ]);
    }
}
