<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiVizualizaceController;
use App\Models\Edihead;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vizualizační stránka deníku (mapa + grafy na jedné stránce).
 *
 * @see EdiVizualizaceController
 */
class EdiVizualizaceTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    public function test_vizualizace_renders(): void
    {
        $head = $this->importSample();

        $this->get(route('edi.vizualizace', $head->ID))
            ->assertOk()
            ->assertSee('Vizualizace deníku')
            ->assertSeeHtml('OK2KJT')
            ->assertSeeHtml('window.__vizConfig');
    }

    public function test_vizualizace_config_contains_points_and_squares(): void
    {
        $head = $this->importSample();

        $html = $this->get(route('edi.vizualizace', $head->ID))->getContent() ?: '';

        // sample.edi: 2 QSO ve velkých čtvercích JN99 a JN89.
        $this->assertStringContainsString('OK2IMH', $html);
        $this->assertStringContainsString('OK2IWU', $html);
        $this->assertStringContainsString('JN99', $html);
        $this->assertStringContainsString('JN89', $html);
    }
}
