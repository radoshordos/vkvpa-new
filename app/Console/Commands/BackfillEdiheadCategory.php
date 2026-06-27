<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Edi\EdiheadCategoryBackfiller;
use Illuminate\Console\Command;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Naplní `edi_head.edi_category_id` zařazením přes CategoryResolver.
 *
 * Párování je 1:1 s importní cestou (tentýž resolver z p_call/p_band/p_sect);
 * nerozpoznané deníky zůstanou NULL. Idempotentní – lze spustit opakovaně.
 */
class BackfillEdiheadCategory extends Command
{
    protected $signature = 'vkvpa:backfill-edihead-category {--dry-run : Jen spočítej, nezapisuj}';

    protected $description = 'Doplní edi_head.edi_category_id zařazením přes CategoryResolver';

    public function handle(EdiheadCategoryBackfiller $backfiller): int
    {
        $dryRun = (bool) $this->option('dry-run');

        intro($dryRun
            ? 'Backfill edi_head.edi_category_id (DRY-RUN, bez zápisu)'
            : 'Backfill edi_head.edi_category_id');

        $r = $backfiller->backfill($dryRun);

        table(
            ['Metrika', 'Počet'],
            [
                ['Celkem řádků', (string) $r->total],
                ['Zařazeno (band/section sedí)', (string) $r->resolved],
                [$dryRun ? 'Ke změně' : 'Zapsáno', (string) $r->changed],
                ['Nezařazeno (NULL)', (string) $r->unresolved],
                ['Nesoulad s číselníkem', (string) $r->mismatched],
            ],
        );

        if ($r->mismatched > 0) {
            warning('Nalezeny řádky, kde zařazená kategorie nesedí na p_band/p_sect (ukázky):');
            table(
                ['p_band | p_sect', 'příklad edi_head.id'],
                array_map(
                    static fn (string $key, int $id): array => [$key, (string) $id],
                    array_keys($r->mismatchSample),
                    array_values($r->mismatchSample),
                ),
            );

            return self::FAILURE;
        }

        outro($dryRun
            ? sprintf('OK (dry-run) – zařaditelných %d, nezařazených %d.', $r->resolved, $r->unresolved)
            : sprintf('Hotovo – zapsáno %d, nezařazených %d zůstává NULL.', $r->changed, $r->unresolved));

        return self::SUCCESS;
    }
}
