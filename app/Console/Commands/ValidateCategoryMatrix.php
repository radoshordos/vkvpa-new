<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Edi\CategoryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Ověří paritu id kategorií mezi `edi_category` a `vkvpa_kategorie`.
 *
 * CategoryResolver páruje podle `edi_category`, ale `vkvpa_data.id_kategorie`
 * stále míří na `vkvpa_kategorie`; id proto musí v obou tabulkách souhlasit.
 * Spouštět po změně kteréhokoli z obou seederů.
 */
class ValidateCategoryMatrix extends Command
{
    protected $signature = 'vkvpa:validate-categories';

    protected $description = 'Ověří paritu id kategorií edi_category ↔ vkvpa_kategorie';

    public function handle(): int
    {
        intro('Validace parity kategorií edi_category ↔ vkvpa_kategorie');

        $expected = CategoryResolver::allCategoryIds();
        sort($expected);

        /** @var list<int> $existing */
        $existing = DB::table('vkvpa_kategorie')
            ->pluck('id')
            ->map(static function (mixed $id): int {
                if (is_numeric($id)) {
                    return (int) $id;
                }
                throw new UnexpectedValueException('Non-numeric category ID: '.get_debug_type($id));
            })
            ->sort()
            ->values()
            ->all();

        $missing = array_diff($expected, $existing);
        $extra = array_diff($existing, $expected);

        if ($missing === [] && $extra === []) {
            $expected
                |> count(...)
                |> (fn ($x) => sprintf('OK – všechna %d ID kategorií existují v databázi.', $x))
                |> outro(...);

            return self::SUCCESS;
        }

        if ($missing !== []) {
            error('Chybějící ID (jsou v edi_category, ale ne ve vkvpa_kategorie):');
            $missing
                |> array_values(...)
                |> (fn ($x) => array_map(fn (int $id): array => [(string) $id, 'chybí v DB'], $x))
                |> (fn ($x) => table(['ID', 'Stav'], $x));
        }

        if ($extra !== []) {
            warning('Navíc ve vkvpa_kategorie (nejsou v edi_category):');
            $extra
                |> array_values(...)
                |> (fn ($x) => array_map(fn (int $id): array => [(string) $id, 'navíc v DB'], $x))
                |> (fn ($x) => table(['ID', 'Stav'], $x));
        }

        return self::FAILURE;
    }
}
