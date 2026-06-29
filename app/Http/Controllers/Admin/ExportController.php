<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EdiHead;
use App\Models\EdiRound;
use App\Support\FileName;
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
        $pocty = EdiHead::query()
            ->whereNotNull('src')
            ->whereRaw("TRIM(src) <> ''")
            ->selectRaw('round_id, COUNT(*) AS pocet')
            ->groupBy('round_id')
            ->pluck('pocet', 'round_id')
            ->map(static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0);

        $kola = EdiRound::query()
            ->orderByDesc('starts_at')
            ->get()
            ->map(static fn (EdiRound $k): array => [
                'id' => $k->id,
                'nazev' => $k->name,
                'starts_at' => $k->starts_at,
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
    public function download(EdiRound $kolo): BinaryFileResponse|StreamedResponse
    {
        $deniky = EdiHead::query()
            ->where('round_id', $kolo->id)
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
            // ID v názvu zajišťuje unikátnost i při shodné značce.
            $entry = sprintf('%s-%d.edi', FileName::sanitize($d->p_call), $d->id);

            $zip->addFromString($entry, (string) $d->src);
        }
        $zip->close();

        $zipName = sprintf('edi-%s-kolo-%d.zip', $kolo->starts_at->format('Y-m-d'), $kolo->id);

        return response()
            ->download($tmp, $zipName, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }
}
