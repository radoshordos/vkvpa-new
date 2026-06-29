<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Edihead;
use App\Services\Edi\EdiParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

/**
 * Doplní prázdné sloupce `p_band`/`p_sect` přeparsováním uložené hlavičky `src`.
 *
 * Některá kola (např. 01–03/2026) byla naimportována bez naparsovaných sloupců –
 * `p_band`/`p_sect` jsou prázdné, ale `src` nese plnou REG1TEST hlavičku. Tenhle
 * příkaz z `src` vytáhne hodnoty a doplní JEN prázdné sloupce (existující data
 * nepřepisuje) – čistě kvalita dat, na edi_heads.edi_category_id (které se bere
 * 1:1 z edi_entries) to nemá vliv. Idempotentní.
 */
class RepairEdiheadBandSectFromSrc extends Command
{
    protected $signature = 'vkvpa:repair-edihead-band-sect {--dry-run : Jen spočítej, nezapisuj}';

    protected $description = 'Doplní prázdné edi_heads.p_band/p_sect přeparsováním src';

    public function handle(EdiParser $parser): int
    {
        $dryRun = (bool) $this->option('dry-run');

        intro($dryRun
            ? 'Doplnění p_band/p_sect ze src (DRY-RUN)'
            : 'Doplnění p_band/p_sect ze src');

        $changed = 0;
        $failed = 0;

        Edihead::query()
            ->select(['id', 'p_band', 'p_sect', 'src'])
            ->where(fn ($q) => $q->where('p_band', '')->orWhere('p_sect', ''))
            ->whereNotNull('src')
            ->where('src', '<>', '')
            ->chunkById(500, function ($heads) use ($parser, $dryRun, &$changed, &$failed): void {
                foreach ($heads as $head) {
                    try {
                        $hdr = $parser->parse((string) $head->src)->header;
                    } catch (Throwable) {
                        $failed++;

                        continue;
                    }

                    $update = [];
                    if ($head->p_band === '' && $hdr->pBand() !== '') {
                        $update['p_band'] = $hdr->pBand();
                    }
                    if ($head->p_sect === '' && $hdr->pSect() !== '') {
                        $update['p_sect'] = $hdr->pSect();
                    }

                    if ($update === []) {
                        continue;
                    }

                    $changed++;
                    if (! $dryRun) {
                        DB::table('edi_heads')->where('id', $head->id)->update($update);
                    }
                }
            });

        outro(sprintf(
            '%s %d řádků%s.',
            $dryRun ? 'K doplnění' : 'Doplněno',
            $changed,
            $failed > 0 ? sprintf(' (%d s neparsovatelným src přeskočeno)', $failed) : '',
        ));

        return self::SUCCESS;
    }
}
