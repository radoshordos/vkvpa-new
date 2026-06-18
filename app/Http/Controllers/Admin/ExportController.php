<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Edihead;
use App\Models\VkvpaKola;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Administrace – Export EDI deníků po kolech v ZIP archivu.
 *
 * Stránka vypíše kola s počtem deníků, které mají uložený zdrojový EDI
 * soubor (`edihead.src`); pro každé kolo lze stáhnout ZIP se všemi jeho
 * deníky. Redigování osobních údajů se zde neprovádí – export je jen pro
 * administrátora (chráněno middleware `admin`).
 */
class ExportController extends Controller
{
    public function index(): Response
    {
        // Počet deníků se zdrojovým EDI souborem na jedno kolo.
        $pocty = Edihead::query()
            ->whereNotNull('src')
            ->whereRaw("TRIM(src) <> ''")
            ->selectRaw('id_kola, COUNT(*) AS pocet')
            ->groupBy('id_kola')
            ->pluck('pocet', 'id_kola')
            ->map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0);

        $kola = VkvpaKola::query()
            ->orderByDesc('datum_konani')
            ->get()
            ->map(static fn (VkvpaKola $k): array => [
                'id' => $k->id,
                'nazev' => $k->nazev,
                'datum_konani' => $k->datum_konani,
                'pocet' => $pocty[$k->id] ?? 0,
            ]);

        return response()->view('pages.admin.export', [
            'active' => 'export.index',
            'kola' => $kola,
        ]);
    }

    /**
     * Stáhne ZIP se všemi EDI deníky daného kola (jen ty se zdrojovým souborem).
     */
    public function download(VkvpaKola $kolo): BinaryFileResponse|StreamedResponse
    {
        $deniky = Edihead::query()
            ->where('id_kola', $kolo->id)
            ->whereNotNull('src')
            ->whereRaw("TRIM(src) <> ''")
            ->orderBy('p_call')
            ->get(['id', 'p_call', 'src']);

        if ($deniky->isEmpty()) {
            abort(404, 'Kolo nemá žádné deníky se zdrojovým EDI souborem.');
        }

        $tmp = (string) tempnam(sys_get_temp_dir(), 'edizip');

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Nepodařilo se vytvořit ZIP archiv.');
        }

        foreach ($deniky as $d) {
            $base = $d->p_call !== '' ? $d->p_call : 'denik';
            $safe = preg_replace('/[^A-Za-z0-9\-]/', '_', $base) ?? 'denik';
            // ID v názvu zajišťuje unikátnost i při shodné značce.
            $entry = sprintf('%s-%d.edi', $safe, $d->id);

            $zip->addFromString($entry, (string) $d->src);
        }
        $zip->close();

        $zipName = sprintf('edi-%s-kolo-%d.zip', $kolo->datum_konani->format('Y-m-d'), $kolo->id);

        return response()
            ->download($tmp, $zipName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
