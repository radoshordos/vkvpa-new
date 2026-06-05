<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VkvpaKategorie;
use App\Services\Edi\CategoryResolver;
use Database\Seeders\VkvpaKategorieTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ověřuje konzistenci matice CategoryResolver::CATEGORIES s databází.
 *
 * Pokud tato sada selže, ID v matici neodpovídají seederu a CategoryResolver
 * tiše přiřazuje špatné kategorie.
 */
class CategoryResolverValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_category_matrix_ids_exist_in_database(): void
    {
        $this->seed(VkvpaKategorieTableSeeder::class);

        $ids = CategoryResolver::allCategoryIds();
        $existing = VkvpaKategorie::whereIn('id', $ids)->get()->map(fn(VkvpaKategorie $k): int => $k->id)->all();
        $missing = array_values(array_diff($ids, $existing));

        $this->assertEmpty(
            $missing,
            'CategoryResolver::CATEGORIES odkazuje na ID, která neexistují v tabulce vkvpa_kategorie: '
            . implode(', ', $missing),
        );
    }

    public function test_all_category_ids_are_unique(): void
    {
        $ids = CategoryResolver::allCategoryIds();

        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'CategoryResolver::CATEGORIES obsahuje duplicitní ID kategorií.',
        );
    }
}
