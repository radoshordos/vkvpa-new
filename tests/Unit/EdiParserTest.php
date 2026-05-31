<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\EdiParseException;
use App\Services\Edi\EdiParser;
use PHPUnit\Framework\TestCase;

/**
 * Testy parseru EDI (Fáze 5). Bez DB – čistá logika.
 */
class EdiParserTest extends TestCase
{
    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/sample.edi');
    }

    public function test_parses_header_fields(): void
    {
        $log = (new EdiParser())->parse($this->fixture());

        $this->assertSame('OK2KJT', $log->header->pCall());
        $this->assertSame('JN99AJ', $log->header->pWWLo());
        $this->assertSame('MULTI', $log->header->pSect());
        $this->assertSame('144 MHz', $log->header->pBand());
        $this->assertSame('ok2ulq@seznam.cz', $log->header->rHBBS());
        $this->assertSame(800, $log->header->sPowe());
        $this->assertFalse($log->header->isQrp());
    }

    public function test_parses_all_qso_lines(): void
    {
        $log = (new EdiParser())->parse($this->fixture());

        $this->assertSame(2, $log->declaredTotal);
        $this->assertSame(2, $log->qsoCount());
        $this->assertSame([], $log->lineErrors);

        $first = $log->qsos[0];
        $this->assertSame('OK2IMH', $first->callSign);
        $this->assertSame('JN99BP', $first->receivedWwl);
        $this->assertSame('2', $first->qsoPoints);
    }

    public function test_uppercases_qso_records(): void
    {
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            . "260315;0800;ok1xyz;1;59;001;59;001;;jn79ab;3;;;;\n[END;]\n";

        $log = (new EdiParser())->parse($edi);

        $this->assertSame('OK1XYZ', $log->qsos[0]->callSign);
        $this->assertSame('JN79AB', $log->qsos[0]->receivedWwl);
    }

    public function test_throws_on_count_mismatch(): void
    {
        // Deklarováno 5, ale jen 2 řádky.
        $edi = str_replace('[QSORecords;2]', '[QSORecords;5]', $this->fixture());

        $this->expectException(EdiParseException::class);
        (new EdiParser())->parse($edi);
    }

    public function test_strips_bom(): void
    {
        $log = (new EdiParser())->parse("\xEF\xBB\xBF" . $this->fixture());

        $this->assertSame('OK2KJT', $log->header->pCall());
    }
}
