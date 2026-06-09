<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\RoundNotFoundException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportZipRequest;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiParser;
use App\Support\VkvpaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use ZipArchive;

/**
 * Administrace – Hromadný import EDI deníků ze ZIP archivu.
 *
 * Zpracování (parsing + DB insert) probíhá sekvenčně, aby nedocházelo
 * k vyčerpání MySQL spojení při větším počtu souborů.
 */
class ImportController extends Controller
{
    public function index(): View
    {
        return view('pages.admin.importy', [
            'active' => 'importy.index',
            'results' => session('import_results'),
        ]);
    }

    public function store(ImportZipRequest $request): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('zip');

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            return back()->withErrors(['zip' => 'Nepodařilo se otevřít ZIP archiv.']);
        }

        $limit = VkvpaSettings::importMaxFiles();
        /** @var list<array<string, mixed>> $items */
        $items = [];

        // Sekvenční čtení ze ZIP + okamžité zpracování každého souboru.
        for ($i = 0; $i < $zip->count() && count($items) < $limit; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $ext = strtolower(pathinfo($stat['name'], PATHINFO_EXTENSION));
            if (! in_array($ext, ['edi', 'txt'], true)) {
                continue;
            }

            $name = basename($stat['name']);
            $maxBytes = VkvpaSettings::ediMaxSizeKb() * 1024;

            if ($stat['size'] > $maxBytes) {
                $items[] = ['file' => $name, 'status' => 'error', 'reason' => 'Soubor přesahuje povolenou velikost.'];

                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false || trim($content) === '') {
                $items[] = ['file' => $name, 'status' => 'error', 'reason' => 'Prázdný nebo nečitelný soubor.'];

                continue;
            }

            try {
                $log = app(EdiParser::class)->parse($content);
            } catch (EdiParseException $e) {
                $items[] = ['file' => $name, 'status' => 'error', 'reason' => $e->getMessage()];

                continue;
            }

            $pcall = $log->header->pCall();

            try {
                // Admin backfill smí importovat i mimo upload okno (stará kola).
                $data = app(ImportEdiAction::class)->execute($log, notify: false, enforceUploadWindow: false);
            } catch (DuplicateEdiException) {
                $items[] = ['file' => $name, 'status' => 'skip', 'znacka' => $pcall, 'reason' => 'Deník pro toto kolo již existuje.'];

                continue;
            } catch (TDateNotContestDayException|RoundNotFoundException|TDateMismatchException $e) {
                $items[] = ['file' => $name, 'status' => 'error', 'reason' => $e->getMessage()];

                continue;
            } catch (UnknownBandException) {
                $items[] = ['file' => $name, 'status' => 'error', 'reason' => "Nerozpoznané pásmo ({$log->header->pBand()})."];

                continue;
            } catch (\Throwable $e) {
                $items[] = ['file' => $name, 'status' => 'error', 'reason' => 'Chyba při importu: '.$e->getMessage()];

                continue;
            }

            $kolo = VkvpaKola::query()->find($data->id_kola);

            $items[] = [
                'file' => $name,
                'status' => 'ok',
                'znacka' => $pcall,
                'kolo' => $kolo instanceof VkvpaKola ? $kolo->nazev : '—',
                'body' => $data->body,
            ];
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
}
