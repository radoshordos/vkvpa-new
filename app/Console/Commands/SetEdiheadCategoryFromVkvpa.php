<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Console\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

/**
 * Nastaví edi_heads.edi_category_id 1:1 z edi_entries.category_id
 * (osiřelé i víceznačné deníky → NULL). Idempotentní.
 */
class SetEdiheadCategoryFromVkvpa extends Command
{
    protected $signature = 'vkvpa:set-edihead-category {--dry-run : Jen spočítej, nezapisuj}';

    protected $description = 'Nastaví edi_heads.edi_category_id 1:1 z edi_entries.category_id';

    public function handle(EdiheadCategoryBackfiller $backfiller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        intro($dryRun
            ? 'edi_heads.edi_category_id ← edi_entries.category_id (DRY-RUN)'
            : 'edi_heads.edi_category_id ← edi_entries.category_id');

        $n = $backfiller->mirrorEdiEntryCategory($dryRun);

        outro($dryRun
            ? sprintf('Ke změně: %d řádků.', $n)
            : sprintf('Změněno %d řádků (1:1 z edi_entries.category_id).', $n));

        return self::SUCCESS;
    }
}
