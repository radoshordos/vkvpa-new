<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VkvpaKategorie;
use App\Services\Edi\CategoryResolver;
use Database\Seeders\VkvpaKategorieTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ověřuje paritu id mezi `edi_category` (zdroj párování) a `vkvpa_kategorie`
 * (na ni stále míří `vkvpa_data.id_kategorie`).
 *
 * Pokud tato sada selže, id v obou tabulkách se rozešla a CategoryResolver by
 * párováním ukazoval na neexistující/špatnou kategorii.
 */
class CategoryResolverValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_category_ids_exist_in_vkvpa_kategorie(): void
    {
        // edi_category seeduje base TestCase; vkvpa_kategorie je proti ní parita
        $this->seed(VkvpaKategorieTableSeeder::class);

        $ids = CategoryResolver::allCategoryIds();
        $existing = VkvpaKategorie::whereIn('id', $ids)->get()->map(fn (VkvpaKategorie $k): int => $k->id)->all();
        $missing = array_values(array_diff($ids, $existing));

        $this->assertEmpty(
            $missing,
            'edi_category odkazuje na id, která neexistují ve vkvpa_kategorie: '
            .implode(', ', $missing),
        );
    }

    public function test_all_category_ids_are_unique(): void
    {
        $ids = CategoryResolver::allCategoryIds();

        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'edi_category obsahuje duplicitní id kategorií.',
        );
    }
}
