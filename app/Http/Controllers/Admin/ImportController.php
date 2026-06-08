<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\ImportEdiAction;
use App\Exceptions\DuplicateEdiException;
use App\Exceptions\EdiParseException;
use App\Exceptions\TDateMismatchException;
use App\Exceptions\TDateNotContestDayException;
use App\Exceptions\UnknownBandException;
use App\Http\Controllers\Controller;
use App\Models\VkvpaKola;
use App\Services\Edi\EdiParser;
use App\Support\VkvpaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\View\View;
use ZipArchive;

/**
 * Administrace – Hromadný import EDI deníků ze ZIP archivu.
 *
 * Fáze čtení ze ZIP je sekvenční (ZipArchive není process-safe); zpracování
 * (parsing + DB insert) probíhá souběžně přes Concurrency::run() – každý soubor
 * v samostatném procesu (process driver) nebo kooperativně (sync/fiber v testech).
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

        $limit = VkvpaSettings::importMaxFiles();
        /** @var list<\Closure(): array<string, mixed>> $tasks */
        $tasks = [];
        /** @var list<array<string, mixed>> $earlyErrors */
        $earlyErrors = [];

        // Fáze 1: sekvenční čtení obsahu ze ZIP.
        for ($i = 0; $i < $zip->count() && (count($tasks) + count($earlyErrors)) < $limit; $i++) {
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
                $earlyErrors[] = ['file' => $name, 'status' => 'error', 'reason' => 'Soubor přesahuje povolenou velikost.'];

                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false || trim($content) === '') {
                $earlyErrors[] = ['file' => $name, 'status' => 'error', 'reason' => 'Prázdný nebo nečitelný soubor.'];

                continue;
            }

            // Closure zachytává jen primitivní řetězce → serializovatelné pro process driver.
            $tasks[] = static function () use ($name, $content): array {
                try {
                    $log = app(EdiParser::class)->parse($content);
                } catch (EdiParseException $e) {
                    return ['file' => $name, 'status' => 'error', 'reason' => $e->getMessage()];
                }

                $pcall = $log->header->pCall();

                try {
                    $data = app(ImportEdiAction::class)->execute($log, notify: false);
                } catch (DuplicateEdiException) {
                    return ['file' => $name, 'status' => 'skip', 'znacka' => $pcall, 'reason' => 'Deník pro toto kolo již existuje.'];
                } catch (TDateNotContestDayException|TDateMismatchException $e) {
                    return ['file' => $name, 'status' => 'error', 'reason' => $e->getMessage()];
                } catch (UnknownBandException) {
                    return ['file' => $name, 'status' => 'error', 'reason' => "Nerozpoznané pásmo ({$log->header->pBand()})."];
                } catch (\Throwable $e) {
                    return ['file' => $name, 'status' => 'error', 'reason' => 'Chyba při importu: '.$e->getMessage()];
                }

                $kolo = VkvpaKola::query()->find($data->id_kola);

                return [
                    'file' => $name,
                    'status' => 'ok',
                    'znacka' => $pcall,
                    'kolo' => $kolo instanceof VkvpaKola ? $kolo->nazev : '—',
                    'body' => $data->body,
                ];
            };
        }
        $zip->close();

        // Fáze 2: souběžné zpracování (parse + DB insert) pro všechny soubory.
        /** @var list<array<string, mixed>> $processed */
        $processed = Concurrency::run($tasks);
        $items = [...$earlyErrors, ...$processed];

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
