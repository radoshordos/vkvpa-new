<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
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

    public function test_dxid_links_each_dx_row_to_matching_domestic_row(): void
    {
        // tuzemské řádky nemají dxid
        $this->assertSame(
            0,
            EdiCategory::query()->where('variant', 'domestic')->whereNotNull('dxid')->count(),
            'Tuzemská kategorie nesmí mít vyplněné dxid.',
        );

        // každý DX řádek míří přes dxid na existující tuzemský řádek se shodným band+section
        foreach (EdiCategory::query()->where('variant', 'dx')->get() as $dx) {
            $this->assertNotNull($dx->dxid, "DX kategorie #{$dx->id} ({$dx->name}) nemá dxid.");

            $domestic = $dx->domesticCounterpart();
            $this->assertNotNull($domestic, "dxid kategorie #{$dx->id} neukazuje na existující řádek.");
            $this->assertSame('domestic', $domestic->variant);
            $this->assertSame($dx->band, $domestic->band);
            $this->assertSame($dx->section, $domestic->section);
        }
    }
}
