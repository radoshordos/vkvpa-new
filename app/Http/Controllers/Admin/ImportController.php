<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\UnknownBandException;
use App\Http\Controllers\Controller;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiParser;
use App\Support\VkvpaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use ZipArchive;

/**
 * Administrace – Hromadný import EDI deníků ze ZIP archivu.
 *
 * Sdílí jádro příjmu deníku s jednotlivým nahráním ({@see ImportEdiAction});
 * navíc jen rozbaluje ZIP, mapuje výsledek na řádek souhrnu a (na rozdíl od
 * jednotlivého nahrání) nerozesílá potvrzovací e-maily.
 */
class ImportController extends Controller
{
    public function __construct(
        private readonly EdiParser $parser,
        private readonly ImportEdiAction $action,
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
            'zip' => ['required', 'file', 'max:'.VkvpaSettings::importMaxSizeKb(), 'mimes:zip'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('zip');

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            return back()->withErrors(['zip' => 'Nepodařilo se otevřít ZIP archiv.']);
        }

        $items = [];
        $limit = VkvpaSettings::importMaxFiles();

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
     * Naparsuje a importuje jeden deník přes sdílenou {@see ImportEdiAction}
     * a přeloží výsledek (nebo výjimku) na řádek souhrnu importu.
     *
     * @return array{file: string, status: string, znacka?: string, kolo?: string, body?: int, reason?: string}
     */
    private function importFile(string $filename, string $content): array
    {
        try {
            $log = $this->parser->parse($content);
        } catch (EdiParseException $e) {
            return ['file' => $filename, 'status' => 'error', 'reason' => $e->getMessage()];
        }

        $pcall = $log->header->pCall();

        try {
            $data = $this->action->execute($log, notify: false);
        } catch (DuplicateEdiException) {
            return ['file' => $filename, 'status' => 'skip', 'znacka' => $pcall, 'reason' => 'Deník pro toto kolo již existuje.'];
        } catch (TDateMismatchException $e) {
            return ['file' => $filename, 'status' => 'error', 'reason' => $e->getMessage()];
        } catch (UnknownBandException) {
            return ['file' => $filename, 'status' => 'error', 'reason' => "Nerozpoznané pásmo ({$log->header->pBand()})."];
        } catch (\Throwable $e) {
            return ['file' => $filename, 'status' => 'error', 'reason' => 'Chyba při importu: '.$e->getMessage()];
        }

        $kolo = VkvpaKola::query()->find($data->id_kola);

        return [
            'file' => $filename,
            'status' => 'ok',
            'znacka' => $pcall,
            'kolo' => $kolo instanceof VkvpaKola ? $kolo->nazev : '—',
            'body' => $data->body,
        ];
    }
}
