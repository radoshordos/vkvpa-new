<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SqlZalohaRequest;
use App\Services\Backup\SqlDumpService;
use App\Support\VkvpaSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administrace – SQL záloha závodních tabulek.
 *
 * Stránka nabídne zaškrtávací seznam povolených tabulek (allowlist
 * `vkvpa.db_backup_table_groups`) s počtem řádků; po odeslání vrátí jeden
 * streamovaný `.sql` soubor se schématem i daty vybraných tabulek (plná,
 * samostatně obnovitelná záloha). Chráněno middleware `admin`.
 */
class ZalohaController extends Controller
{
    public function __construct(private readonly SqlDumpService $dump) {}

    public function index(): View
    {
        $groups = [];
        foreach (VkvpaSettings::dbBackupTableGroups() as $group => $tables) {
            $rows = [];
            foreach ($tables as $table) {
                $rows[] = [
                    'name' => $table,
                    'count' => DB::table($table)->count(),
                ];
            }
            $groups[$group] = $rows;
        }

        return view('pages.admin.zaloha', [
            'active' => 'zaloha.index',
            'groups' => $groups,
        ]);
    }

    /**
     * Vygeneruje a stáhne SQL dump vybraných tabulek (schéma + data).
     *
     * Výstup je streamovaný; volitelně se průběžně komprimuje gzipem
     * (`deflate_add`), takže ani u velkých tabulek (`edi_lines`) nedrží celý
     * dump v paměti. Časový limit běhu se pro generování vypíná.
     */
    public function download(SqlZalohaRequest $request): StreamedResponse
    {
        $tables = $request->selectedTables();
        $gzip = $request->wantsGzip();

        $timestamp = Carbon::now()->format('Y-m-d-Hi');
        $filename = sprintf('vkvpa-zaloha-%s.sql%s', $timestamp, $gzip ? '.gz' : '');

        return response()->streamDownload(function () use ($tables, $gzip): void {
            @set_time_limit(0);

            if ($gzip && ($ctx = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6])) !== false) {
                foreach ($this->dump->stream($tables) as $chunk) {
                    echo deflate_add($ctx, $chunk, ZLIB_NO_FLUSH);
                    $this->flush();
                }
                echo deflate_add($ctx, '', ZLIB_FINISH);

                return;
            }

            foreach ($this->dump->stream($tables) as $chunk) {
                echo $chunk;
                $this->flush();
            }
        }, $filename, [
            'Content-Type' => $gzip ? 'application/gzip' : 'application/sql; charset=utf-8',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Vytlačí dosud vygenerovaný výstup k prohlížeči, aby se nehromadil
     * v paměti. V testech se přeskočí, aby `streamedContent()` zachytil vše.
     */
    private function flush(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
