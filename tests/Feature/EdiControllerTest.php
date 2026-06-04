<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Feature testy pro EdiController – zobrazení formuláře a validace nahrávání.
 * Testování úspěšného importu a business validací je v HlaseniTest.
 */
class EdiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_form_is_accessible(): void
    {
        $this->get(route('read_edi'))->assertOk();
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->post('/edi', [])
            ->assertSessionHasErrors('upload');

        $this->assertSame(0, Edihead::count());
    }

    public function test_invalid_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('denik.pdf', '[REG1TEST;1]');

        $this->post('/edi', ['upload' => $file])
            ->assertSessionHasErrors('upload');

        $this->assertSame(0, Edihead::count());
    }

    public function test_corrupt_edi_content_returns_parse_error(): void
    {
        $file = UploadedFile::fake()->createWithContent('bad.edi', 'toto neni edi soubor');

        $this->post('/edi', ['upload' => $file])
            ->assertSessionHasErrors('upload');

        $this->assertSame(0, Edihead::count());
    }
}
