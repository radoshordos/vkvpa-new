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
use App\Models\Edihead;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiReducer;
use App\Services\Edi\EdiValidator;
use App\Support\VkvpaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'upload' => ['required', 'file', 'max:'.VkvpaSettings::ediMaxSizeKb(), 'extensions:edi,txt'],
        ]);

        $content = (string) file_get_contents($request->file('upload')->getRealPath());

        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $e) {
            return back()
                ->withErrors(['upload' => $e->getMessage()])
                ->with('lineErrors', $e->lineErrors);
        }

        try {
            $row = $this->action->execute($log);
        } catch (TDateNotContestDayException|RoundNotFoundException|TDateMismatchException|DuplicateEdiException|UnknownBandException $e) {
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
     *   – nepřihlášený, žádné aktivní kolo: přesměrování na přihlášení,
     *   – přihlášený, žádné aktivní kolo: povolen.
     */
    public function zobrazit(Edihead $head): Response|RedirectResponse
    {
        if ($redirect = $this->checkEdiAccess()) {
            return $redirect;
        }

        return $this->ediResponse($head, (string) $head->src, $head->PCall, 'edi');
    }

    /**
     * Zobrazí REDUKOVANÝ EDI soubor (oříznutý na závodní okno 08:00–11:00 UTC).
     * Stejná přístupová pravidla jako {@see zobrazit()}.
     */
    public function zobrazitRedukovany(Edihead $head): Response|RedirectResponse
    {
        if ($redirect = $this->checkEdiAccess()) {
            return $redirect;
        }

        $src = (string) $head->src;
        $reduced = $src === '' ? '' : $this->reducer->reduce($src);

        return $this->ediResponse($head, $reduced, $head->PCall, 'edir');
    }

    /**
     * Vrátí redirect (pokud je přístup odepřen) nebo null (přístup povolen).
     * Admin má vždy přístup. Ostatní uživatelé jsou blokováni v době
     * otevřeného upload window; mimo window se vyžaduje přihlášení.
     *
     * @return RedirectResponse|null null = přístup povolen
     */
    private function checkEdiAccess(): ?RedirectResponse
    {
        if (auth()->user()?->is_admin) {
            return null;
        }

        if (VkvpaKola::existujeAktivni()) {
            abort(403);
        }

        if (! auth()->check()) {
            return redirect()->route('login');
        }

        return null;
    }

    private function ediResponse(Edihead $head, string $content, string $pcall, string $variant): Response
    {
        if (trim($content) === '') {
            abort(404, 'EDI soubor není pro tento deník k dispozici.');
        }

        $base = $pcall !== '' ? $pcall : 'denik';
        // Sanitize: keep only safe ASCII chars to prevent header injection.
        $safe = preg_replace('/[^A-Za-z0-9\-]/', '_', $base) ?? 'denik';
        $filename = sprintf('%s-%d-%s.edi', $safe, $head->ID, $variant);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "inline; filename=\"{$filename}\"; filename*=UTF-8''{$filename}",
        ]);
    }
}
