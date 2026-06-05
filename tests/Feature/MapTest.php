<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\MapController;
use App\Models\Edihead;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tři mapové pohledy: M (ježek), N (špendlíky), S (velké čtverce).
 *
 * @see MapController
 */
class MapTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    public function test_jezek_map_renders(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.mapa.jezek', $head->ID))
            ->assertOk()
            ->assertSeeHtml('Mapa spojení')
            ->assertSeeHtml('OK2KJT')
            ->assertSeeHtml('window.__mapConfig')
            ->assertSee('ježek');
    }

    public function test_spendliky_map_renders(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.mapa.spendliky', $head->ID))
            ->assertOk()
            ->assertSee('špendlíky')
            ->assertSeeHtml('OK2KJT');
    }

    public function test_lokatory_map_renders_with_big_squares(): void
    {
        $head = $this->importSample();

        // sample.edi má QSO ve velkých čtvercích JN99 a JN89.
        $this->get(route('edi.mapa.lokatory', $head->ID))
            ->assertOk()
            ->assertSee('velké čtverce')
            ->assertSee('JN99')
            ->assertSee('JN89');
    }
}
