<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiBand;
use App\Models\EdiCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Číselník pásem `edi_bands` a jeho provázání na `edi_category.band_id`.
 */
class EdiBandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ciselnik_je_naseedovany_z_kanonickeho_seznamu(): void
    {
        $this->assertSame(count(EdiBand::CANONICAL), EdiBand::query()->count());

        $band = EdiBand::query()->where('token', '144')->firstOrFail();
        $this->assertSame('144 MHz', $band->name);
    }

    public function test_kazda_seedovana_kategorie_ma_band_id_odpovidajici_textu_band(): void
    {
        $kategorie = EdiCategory::query()->with('ediBand')->get();

        $this->assertNotEmpty($kategorie);

        foreach ($kategorie as $kat) {
            $this->assertNotNull($kat->band_id, "Kategorie {$kat->id} nemá band_id.");
            $this->assertNotNull($kat->ediBand);
            $this->assertSame($kat->band, $kat->ediBand->name);
        }
    }

    public function test_relace_categories_vraci_kategorie_pasma(): void
    {
        $band = EdiBand::query()->where('token', '144')->firstOrFail();

        // 144 MHz má v seedu 4 kategorie: SO/MO × domestic/dx.
        $this->assertSame(4, $band->categories()->count());
    }
}
