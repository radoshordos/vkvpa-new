<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Console\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

/**
 * Vynuluje `edi_head.edi_category_id` tam, kde se kategorie z hlavičky neshoduje
 * s kategorií příspěvku (`vkvpa_data.id_kategorie`). Osiřelé hlavičky bez
 * příspěvku se nedotýkají. Idempotentní.
 */
class NullifyEdiheadCategoryConflicts extends Command
{
    protected $signature = 'vkvpa:nullify-edihead-category-conflicts {--dry-run : Jen spočítej, nezapisuj}';

    protected $description = 'Vynuluje edi_head.edi_category_id u rozdílů vůči vkvpa_data.id_kategorie';

    public function handle(EdiheadCategoryBackfiller $backfiller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        intro($dryRun
            ? 'Nulování konfliktů edi_head.edi_category_id ↔ vkvpa_data (DRY-RUN)'
            : 'Nulování konfliktů edi_head.edi_category_id ↔ vkvpa_data');

        $n = $backfiller->nullifyVkvpaDataConflicts($dryRun);

        outro($dryRun
            ? sprintf('K vynulování: %d řádků.', $n)
            : sprintf('Vynulováno %d řádků (kategorie z hlavičky ≠ kategorie příspěvku).', $n));

        return self::SUCCESS;
    }
}
