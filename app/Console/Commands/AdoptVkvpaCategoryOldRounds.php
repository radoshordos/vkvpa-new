<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Console\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

/**
 * U kol konaných před rokem 2026 převezme edi_head.edi_category_id z autoritativní
 * kategorie příspěvku (vkvpa_data.id_kategorie). Idempotentní.
 */
class AdoptVkvpaCategoryOldRounds extends Command
{
    protected $signature = 'vkvpa:adopt-vkvpa-category-old-rounds {--dry-run : Jen spočítej, nezapisuj}';

    protected $description = 'U kol <2026 nastaví edi_head.edi_category_id = vkvpa_data.id_kategorie';

    public function handle(EdiheadCategoryBackfiller $backfiller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        intro($dryRun
            ? 'Převzetí kategorie z vkvpa_data pro kola <2026 (DRY-RUN)'
            : 'Převzetí kategorie z vkvpa_data pro kola <2026');

        $n = $backfiller->adoptVkvpaDataForOldRounds($dryRun);

        outro($dryRun
            ? sprintf('Ke změně: %d řádků.', $n)
            : sprintf('Změněno %d řádků (edi_head.edi_category_id ← vkvpa_data.id_kategorie).', $n));

        return self::SUCCESS;
    }
}
