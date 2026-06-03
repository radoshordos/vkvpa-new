<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\EdiParseException;
use App\Exceptions\UnknownBandException;
use App\Models\Edihead;
use App\Models\VkvpaData;
use App\Services\Edi\CategoryResolver;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiReducer;
use App\Services\Scoring\ScoringService;
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
        private readonly EdiImportService $importer,
        private readonly ScoringService $scoring,
        private readonly EdiReducer $reducer,
        private readonly CategoryResolver $categories,
    ) {}

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
        } catch (EdiParseException $ediParseException) {
            return back()
                ->withErrors(['upload' => $ediParseException->getMessage()])
                ->with('lineErrors', $ediParseException->lineErrors);
        }

        $h = $log->header;
        $pcall = $h->pCall();
        $idKola = $this->scoring->koloForTDate($h->tDate()) ?? 0;

        // Duplicitní deník: stejná značka už má pro toto kolo nahraný EDI deník.
        if (VkvpaData::query()->where('EDI', true)->where('znacka', $pcall)->where('id_kola', $idKola)->exists()) {
            return back()->withErrors([
                'upload' => "Deník stanice {$pcall} už byl pro toto kolo nahrán – soubor již existuje.",
            ]);
        }

        // Kategorie z hlavičky (pásmo + sekce + DX dle prefixu značky).
        // Nerozpoznané pásmo → deník odmítneme; nerozpoznaná sekce → 0 (doplní admin).
        try {
            $idKategorie = $this->categories->resolve($pcall, $h->pBand(), $h->pSect()) ?? 0;
        } catch (UnknownBandException) {
            return back()->withErrors([
                'upload' => 'Nerozpoznané pásmo v deníku ('.$h->pBand().') – nelze určit kategorii. Oprav PBand a nahraj znovu.',
            ]);
        }

        $head = $this->importer->import($log);
        $score = $this->scoring->scoreEdi($head);

        // Rezervovaný řádek (schvaleno=0) – formulář ho převezme a doplní.
        $row = VkvpaData::create([
            'id_kola' => $idKola,
            'id_kategorie' => $idKategorie,
            'znacka' => $h->pCall(),
            'locator' => $h->pWWLo(),
            'jmeno' => $h->rName(),
            'mail' => $h->rEmail(),
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

    /**
     * Sestaví text/plain odpověď s EDI obsahem (zobrazení v prohlížeči, ne stažení).
     */
    private function ediResponse(Edihead $head, string $content, string $pcall, string $variant): Response
    {
        if (trim($content) === '') {
            abort(404, 'EDI soubor není pro tento deník k dispozici.');
        }

        $filename = sprintf('%s-%d-%s.edi', $pcall !== '' ? $pcall : 'denik', $head->ID, $variant);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
