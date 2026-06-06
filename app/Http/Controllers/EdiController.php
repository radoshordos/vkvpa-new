<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\UnknownBandException;
use App\Models\Edihead;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiReducer;
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
        } catch (TDateMismatchException|DuplicateEdiException|UnknownBandException $e) {
            return back()->withErrors(['upload' => $e->getMessage()]);
        }

        // ID vlastněného řádku v session – povolí jeho editaci i nepřihlášenému.
        $request->session()->put('owned_data_id', $row->id);

        return redirect()->route('hlaseni.index', ['import' => 'success']);
    }

    /**
     * Zobrazí původní EDI soubor deníku – akce „EDI" ve výsledkové listině.
     *
     * Endpoint: GET /edi/{head}/soubor  (name: edi.soubor)
     * Vstup:    {head} = ID deníku v `edihead` (route-model-binding)
     * Efekt:    žádný (jen čtení uloženého `edihead.src`)
     * Návrat:   text/plain s původním obsahem EDI; 404 pokud zdroj chybí.
     */
    public function zobrazit(Edihead $head): Response
    {
        return $this->ediResponse($head, (string) $head->src, $head->PCall, 'edi');
    }

    /**
     * Zobrazí REDUKOVANÝ EDI soubor – akce „EDIR" ve výsledkové listině.
     *
     * Endpoint: GET /edi/{head}/soubor-redukovany  (name: edi.soubor.redukovany)
     * Vstup:    {head} = ID deníku v `edihead` (route-model-binding)
     * Efekt:    žádný; z `edihead.src` se za běhu ořežou QSO mimo závodní okno
     *           08:00–11:00 UTC ({@see EdiReducer}). Tato oříznutá podoba je
     *           zároveň ta, podle které se deník vyhodnocuje.
     * Návrat:   text/plain s oříznutým EDI; 404 pokud zdroj chybí.
     */
    public function zobrazitRedukovany(Edihead $head): Response
    {
        $src = (string) $head->src;
        $reduced = $src === '' ? '' : $this->reducer->reduce($src);

        return $this->ediResponse($head, $reduced, $head->PCall, 'edir');
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
