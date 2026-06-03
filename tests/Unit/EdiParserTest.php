<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\EdiParseException;
use App\Services\Edi\EdiParser;
use PHPUnit\Framework\TestCase;

/**
 * Testy parseru EDI. Bez DB – čistá logika.
 */
class EdiParserTest extends TestCase
{
    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/sample.edi');
    }

    public function test_parses_header_fields(): void
    {
        $log = new EdiParser()->parse($this->fixture());

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
        $log = new EdiParser()->parse($this->fixture());

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

        $log = new EdiParser()->parse($edi);

        $this->assertSame('OK1XYZ', $log->qsos[0]->callSign);
        $this->assertSame('JN79AB', $log->qsos[0]->receivedWwl);
    }

    public function test_throws_on_count_mismatch(): void
    {
        // Deklarováno 5, ale jen 2 řádky.
        $edi = str_replace('[QSORecords;2]', '[QSORecords;5]', $this->fixture());

        $this->expectException(EdiParseException::class);
        new EdiParser()->parse($edi);
    }

    public function test_strips_bom(): void
    {
        $log = new EdiParser()->parse("\xEF\xBB\xBF" . $this->fixture());

        $this->assertSame('OK2KJT', $log->header->pCall());
    }

    public function test_converts_windows1250_to_utf8(): void
    {
        $utf8 = "[REG1TEST;1]\nPCall=OK1ABC\nRName=Tomáš Novák\n[QSORecords;1]\n"
            . "260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n[END;]\n";
        $win1250 = (string) iconv('UTF-8', 'Windows-1250', $utf8);

        // Pojistka: vstup opravdu není UTF-8.
        $this->assertFalse(mb_check_encoding($win1250, 'UTF-8'));

        $log = new EdiParser()->parse($win1250);

        $this->assertSame('Tomáš Novák', $log->header->rName());
    }

    public function test_reports_file_without_edi_structure(): void
    {
        // Deklaruje 2 QSO, ale žádný řádek nemá platnou strukturu (odeslaný .txt).
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;2]\ntohle neni QSO\nani tohle\n[END;]\n";

        $this->expectException(EdiParseException::class);
        $this->expectExceptionMessage('nevypadá jako platný EDI');
        new EdiParser()->parse($edi);
    }

    public function test_skips_error_marked_lines(): void
    {
        // Deklarováno 3, ale jeden řádek má značku „ERROR" (vadné spojení).
        // Ten se ignoruje a počet QSO přesto sedí (3 = 2 platné + 1 ignorovaný).
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;3]\n"
            . "260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n"
            . "260315;;ERROR;1;59;002;;;;JN79AB;0;;;;\n"
            . "260315;0802;OK2DEF;1;59;003;59;002;;JN89CD;2;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        $this->assertSame(2, $log->qsoCount());          // jen platná spojení
        $this->assertSame(3, $log->declaredTotal);
        $this->assertCount(1, $log->ignoredLines);       // jeden ignorovaný řádek
        $this->assertSame([], $log->lineErrors);
        $this->assertSame('OK1XYZ', $log->qsos[0]->callSign);
        $this->assertSame('OK2DEF', $log->qsos[1]->callSign);
    }
}
