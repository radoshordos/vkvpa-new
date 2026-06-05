<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\EdiParseException;
use App\Exceptions\UnknownBandException;
use App\Http\Controllers\Controller;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Edi\CategoryResolver;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiQso;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use ZipArchive;

/** Administrace – Hromadný import EDI deníků ze ZIP archivu. */
class ImportController extends Controller
{
    public function __construct(
        private readonly EdiParser $parser,
        private readonly EdiImportService $importer,
        private readonly ScoringService $scoring,
        private readonly CategoryResolver $categories,
    ) {}

    public function index(): View
    {
        return view('pages.admin.importy', [
            'active' => 'importy.index',
            'results' => session('import_results'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'zip' => ['required', 'file', 'max:20480', 'mimes:zip'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('zip');

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            return back()->withErrors(['zip' => 'Nepodařilo se otevřít ZIP archiv.']);
        }

        $items = [];
        $limit = 200;

        for ($i = 0; $i < $zip->count() && count($items) < $limit; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $ext = strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION));
            if (! in_array($ext, ['edi', 'txt'], true)) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false || trim($content) === '') {
                $items[] = ['file' => basename($stat['name']), 'status' => 'error', 'reason' => 'Prázdný nebo nečitelný soubor.'];

                continue;
            }

            $items[] = $this->importFile(basename($stat['name']), $content);
        }

        $zip->close();

        return redirect()
            ->route('importy.index')
            ->with('import_results', [
                'total' => count($items),
                'imported' => count(array_filter($items, static fn (array $r): bool => $r['status'] === 'ok')),
                'skipped' => count(array_filter($items, static fn (array $r): bool => $r['status'] === 'skip')),
                'errors' => count(array_filter($items, static fn (array $r): bool => $r['status'] === 'error')),
                'items' => $items,
            ]);
    }

    /**
     * @return array{file: string, status: string, znacka?: string, kolo?: string, body?: int, reason?: string}
     */
    private function importFile(string $filename, string $content): array
    {
        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $e) {
            return ['file' => $filename, 'status' => 'error', 'reason' => $e->getMessage()];
        }

        $h = $log->header;
        $pcall = $h->pCall();

        $tdateDay = substr(trim($h->tDate()), 2, 6);
        $qsoDays = array_values(array_unique(
            array_map(static fn (EdiQso $q): string => $q->date, $log->qsos)
        ));
        if ($tdateDay !== '' && $qsoDays !== [] && ! in_array($tdateDay, $qsoDays, true)) {
            return ['file' => $filename, 'status' => 'error', 'reason' => "TDate ({$h->tDate()}) neodpovídá datům QSO."];
        }

        $idKola = $this->scoring->koloForTDate($h->tDate()) ?? 0;

        if (VkvpaData::query()->where('EDI', true)->where('znacka', $pcall)->where('id_kola', $idKola)->exists()) {
            return ['file' => $filename, 'status' => 'skip', 'znacka' => $pcall, 'reason' => 'Deník pro toto kolo již existuje.'];
        }

        try {
            $idKategorie = $this->categories->resolve($pcall, $h->pBand(), $h->pSect()) ?? 0;
        } catch (UnknownBandException) {
            return ['file' => $filename, 'status' => 'error', 'reason' => "Nerozpoznané pásmo ({$h->pBand()})."];
        }

        try {
            $head = $this->importer->import($log);
            $score = $this->scoring->scoreEdi($head);
        } catch (\Throwable $e) {
            return ['file' => $filename, 'status' => 'error', 'reason' => 'Chyba při importu: '.$e->getMessage()];
        }

        VkvpaData::create([
            'id_kola' => $idKola,
            'id_kategorie' => $idKategorie,
            'znacka' => $pcall,
            'locator' => $h->pWWLo(),
            'jmeno' => $h->rName(),
            'mail' => $h->rEmail(),
            'telefon' => $h->rPhon(),
            'soapbox' => $h->get('RSoap'),
            'pocet' => $score->pocet,
            'nasobice' => $score->nasobice,
            'bodu_za_qso' => $score->boduZaQso,
            'body' => $score->body,
            'qrp' => $h->isQrp(),
            'EDI' => true,
            'EDI_ID' => $head->ID,
            'schvaleno' => false,
        ]);

        $kolo = VkvpaKola::query()->find($idKola);

        return [
            'file' => $filename,
            'status' => 'ok',
            'znacka' => $pcall,
            'kolo' => $kolo instanceof VkvpaKola ? $kolo->nazev : '—',
            'body' => $score->body,
        ];
    }
}
