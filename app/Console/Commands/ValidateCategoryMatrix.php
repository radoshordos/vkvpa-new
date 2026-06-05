<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Edi\CategoryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ověří, že všechna ID kategorií z CategoryResolver::CATEGORIES existují v tabulce vkvpa_kategorie.
 *
 * Spouštět po každé změně CategoryResolver::CATEGORIES nebo po změně seederu.
 */
class ValidateCategoryMatrix extends Command
{
    protected $signature = 'vkvpa:validate-categories';

    protected $description = 'Ověří konzistenci matice CategoryResolver vůči tabulce vkvpa_kategorie';

    public function handle(): int
    {
        $expected = CategoryResolver::allCategoryIds();
        sort($expected);

        /** @var list<int> $existing */
        $existing = DB::table('vkvpa_kategorie')
            ->pluck('id')
            ->map(static function (mixed $id): int {
                if (is_numeric($id)) {
                    return (int) $id;
                }
                throw new \UnexpectedValueException('Non-numeric category ID: '.get_debug_type($id));
            })
            ->sort()
            ->values()
            ->all();

        $missing = array_diff($expected, $existing);
        $extra = array_diff($existing, $expected);

        if ($missing === [] && $extra === []) {
            $this->info(sprintf('OK – všechna %d ID kategorií existují v databázi.', count($expected)));

            return self::SUCCESS;
        }

        if ($missing !== []) {
            $this->error('Chybějící ID (jsou v matici, ale ne v DB): '.implode(', ', $missing));
        }

        if ($extra !== []) {
            $this->warn('Navíc v DB (nejsou v matici): '.implode(', ', $extra));
        }

        return self::FAILURE;
    }
}
