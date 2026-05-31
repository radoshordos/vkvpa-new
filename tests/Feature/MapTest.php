<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_page_renders_with_points(): void
    {
        $edi = (string) file_get_contents(__DIR__ . '/../fixtures/sample.edi');
        $head = (new EdiImportService())->import((new EdiParser())->parse($edi));

        $this->get(route('edi.mapa', $head->ID))
            ->assertOk()
            ->assertSee('Mapa spojení', false)
            ->assertSee('OK2KJT', false)
            ->assertSee('leaflet@1.9.4', false); // nejnovější stabilní Leaflet
    }
}
