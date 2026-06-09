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
 * Test perzistence importu EDI.
 */
class EdiImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_header_and_lines(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        $log = new EdiParser()->parse($edi);
        $head = new EdiImportService()->import($log);

        $this->assertInstanceOf(Edihead::class, $head);
        $this->assertSame('OK2KJT', $head->p_call);
        $this->assertSame(800, (int) $head->s_powe);

        $this->assertSame(2, Ediline::where('edihead_id', $head->id)->count());

        $first = Ediline::where('edihead_id', $head->id)->orderBy('id')->first();
        $this->assertNotNull($first);
        $this->assertSame('OK2IMH', $first->call_sign);
        $this->assertSame('JN99BP', $first->receivedWwl);
        $this->assertSame(2, $first->qsoPoints);

        $this->assertCount(2, $head->lines);
    }

    public function test_imports_edi_with_no_qso_lines(): void
    {
        $edi = implode("\n", [
            '[REG1TEST;1]',
            'TDate=20260315;20260315',
            'PCall=OK1TEST',
            'PWWLo=JN79GB',
            'PSect=SINGLE',
            'PBand=144 MHz',
            'RName=Test',
            'RPhon=',
            'RHBBS=',
            'SPowe=5',
            '[QSORecords;0]',
            '[END;]',
        ])."\n";

        $log = new EdiParser()->parse($edi);
        $head = new EdiImportService()->import($log);

        $this->assertInstanceOf(Edihead::class, $head);
        $this->assertSame('OK1TEST', $head->p_call);
        $this->assertSame(0, Ediline::where('edihead_id', $head->id)->count());
    }

    public function test_raw_source_is_persisted(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        $log = new EdiParser()->parse($edi);
        $head = new EdiImportService()->import($log);

        $this->assertNotEmpty($head->src);
        $this->assertStringContainsString('OK2KJT', (string) $head->src);
    }
}
