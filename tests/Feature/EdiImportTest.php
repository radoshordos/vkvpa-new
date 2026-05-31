<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
use App\Models\Ediline;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test perzistence importu EDI (Fáze 5).
 */
class EdiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_header_and_lines(): void
    {
        $edi = (string) file_get_contents(__DIR__ . '/../fixtures/sample.edi');

        $log = (new EdiParser())->parse($edi);
        $head = (new EdiImportService())->import($log);

        $this->assertInstanceOf(Edihead::class, $head);
        $this->assertSame('OK2KJT', $head->PCall);
        $this->assertSame(800, (int) $head->SPowe);

        $this->assertSame(2, Ediline::where('IDS', $head->ID)->count());

        $first = Ediline::where('IDS', $head->ID)->orderBy('ID')->first();
        $this->assertSame('OK2IMH', $first->CallSign);
        $this->assertSame('JN99BP', $first->{'Received-WWL'});
        $this->assertSame(2, (int) $first->{'QSO-Points'});

        // Vztah z Fáze 1 funguje.
        $this->assertCount(2, $head->lines);
    }
}
