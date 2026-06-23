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
        return (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
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
            ."260315;0800;ok1xyz;1;59;001;59;001;;jn79ab;3;;;;\n[END;]\n";

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
        $log = new EdiParser()->parse("\xEF\xBB\xBF".$this->fixture());

        $this->assertSame('OK2KJT', $log->header->pCall());
    }

    public function test_converts_windows1250_to_utf8(): void
    {
        $utf8 = "[REG1TEST;1]\nPCall=OK1ABC\nRName=Tomáš Novák\n[QSORecords;1]\n"
            ."260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n[END;]\n";
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
            ."260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n"
            ."260315;;ERROR;1;59;002;;;;JN79AB;0;;;;\n"
            ."260315;0802;OK2DEF;1;59;003;59;002;;JN89CD;2;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        $this->assertSame(2, $log->qsoCount());          // jen platná spojení
        $this->assertSame(3, $log->declaredTotal);
        $this->assertCount(1, $log->ignoredLines);       // jeden ignorovaný řádek
        $this->assertSame([], $log->lineErrors);
        $this->assertSame('OK1XYZ', $log->qsos[0]->callSign);
        $this->assertSame('OK2DEF', $log->qsos[1]->callSign);
    }

    public function test_rejects_qso_with_invalid_maidenhead_locator(): void
    {
        // ZZ99AJ má první 2 písmena mimo A–R → lokátor odmítnut, ale QSO s JN79AB projde.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;2]\n"
            ."260315;0800;OK1ABC;1;59;001;59;001;;JN79AB;3;;;;\n"
            ."260315;0810;OK1XYZ;1;59;002;59;002;;ZZ99AJ;3;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        $this->assertSame(1, $log->qsoCount());                    // jen platné QSO
        $this->assertSame('JN79AB', $log->qsos[0]->receivedWwl);
        $this->assertCount(1, $log->lineErrors);                   // ZZ99AJ odmítnuto
        $this->assertStringContainsString('ZZ99AJ', $log->lineErrors[0]);
        $this->assertStringContainsString('OK1XYZ', $log->lineErrors[0]);
    }

    public function test_accepts_valid_maidenhead_boundary_locators(): void
    {
        // RA09AX: R je poslední platné písmeno (A–R), A09 digit, AX je poslední platný subčtverec.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            ."260315;0800;OK1ABC;1;59;001;59;001;;RA09AX;3;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        $this->assertSame(1, $log->qsoCount());
        $this->assertSame([], $log->lineErrors);
    }

    public function test_rejects_import_when_record_has_too_few_separators(): void
    {
        // Chybí jedno pole → jen 13 středníků místo 14 → strukturální chyba.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            ."260315;0800;OK1XYZ;1;59;001;59;001;JN79AB;3;;;;\n[END;]\n";

        $this->expectException(EdiParseException::class);
        $this->expectExceptionMessage('15 polí');
        new EdiParser()->parse($edi);
    }

    public function test_rejects_import_when_record_has_too_many_separators(): void
    {
        // Přebytečný středník navíc → 15 středníků místo 14.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            ."260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;;\n[END;]\n";

        try {
            new EdiParser()->parse($edi);
            $this->fail('Očekávána EdiParseException.');
        } catch (EdiParseException $e) {
            $this->assertCount(1, $e->lineErrors);
            $this->assertStringContainsString('15 oddělovačů', $e->lineErrors[0]);
        }
    }

    public function test_reports_bad_date_even_when_line_fails_full_pattern(): void
    {
        // Reálný případ: 9místné datum „202606021" a navíc prázdné pole bodů.
        // Řádek neprojde QSO_PATTERN (prázdné body), ale datum musí dostat
        // konkrétní hlášku, ne obecné „nevypadá jako platný EDI".
        $edi = "[REG1TEST;1]\nPCall=OK2PKD\n[QSORecords;1]\n"
            ."202606021;1050;OK1FPC;2;599;001;599;007;;JN79NU;;;;;\n[END;]\n";

        try {
            new EdiParser()->parse($edi);
            $this->fail('Očekávána EdiParseException.');
        } catch (EdiParseException $e) {
            $this->assertCount(1, $e->lineErrors);
            $this->assertStringContainsString('202606021', $e->lineErrors[0]);
            $this->assertStringContainsString('OK1FPC', $e->lineErrors[0]);
            $this->assertStringContainsString('RRMMDD', $e->getMessage());
        }
    }

    public function test_accepts_qso_with_empty_qso_points(): void
    {
        // VUSC for Win nechává pole „body za QSO" prázdné. Spojení je jinak
        // kompletní → naimportuje se (body si stejně počítáme z lokátorů).
        $edi = "[REG1TEST;1]\nPCall=OK2PKD\n[QSORecords;1]\n"
            ."260621;1050;OK1FPC;2;599;001;599;007;;JN79NU;;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        $this->assertSame(1, $log->qsoCount());
        $this->assertSame('OK1FPC', $log->qsos[0]->callSign);
        $this->assertSame('JN79NU', $log->qsos[0]->receivedWwl);
        $this->assertSame('', $log->qsos[0]->qsoPoints);
        $this->assertSame([], $log->lineErrors);
    }

    public function test_tolerates_qso_line_with_empty_received_rst(): void
    {
        // FM spojení bez přijatého RST/čísla (reálné v historických denících):
        // platné datum i body → řádek se tolerantně přeskočí, ale je-li to jediné
        // QSO, zůstane stávající chování (žádné platné spojení → výjimka).
        $edi = "[REG1TEST;1]\nPCall=OK2BUB\n[QSORecords;2]\n"
            ."260118;0801;OK2TVP;6;59;001;;;;JN99EQ;2;;N;N;\n"
            ."260118;0802;OK2VIR;1;59;002;59;003;;JN99DU;2;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        // Druhé (úplné) QSO projde, první (bez přijatého RST) se přeskočí.
        $this->assertSame(1, $log->qsoCount());
        $this->assertSame('OK2VIR', $log->qsos[0]->callSign);
        $this->assertCount(1, $log->ignoredLines);
    }

    public function test_rejects_import_when_date_has_four_digit_year(): void
    {
        // Čtyřmístný rok (RRRRMMDD) místo RRMMDD → celý import se odmítne
        // s vysvětlující hláškou pro závodníka.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            ."20260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n[END;]\n";

        $this->expectException(EdiParseException::class);
        $this->expectExceptionMessage('RRMMDD');
        new EdiParser()->parse($edi);
    }

    public function test_rejects_import_when_time_not_four_digits(): void
    {
        // Čas „800" místo „0800" (3 číslice) → nesprávný formát HHMM → odmítnuto.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            ."260315;800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n[END;]\n";

        $this->expectException(EdiParseException::class);
        $this->expectExceptionMessage('HHMM');
        new EdiParser()->parse($edi);
    }

    public function test_rejects_import_when_date_time_overflows(): void
    {
        // Délka 6/4 sedí, ale 13. měsíc a 25. hodina neexistují (createFromFormat
        // by je tiše „převalil") → odmítnuto.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;1]\n"
            ."261331;2599;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n[END;]\n";

        $this->expectException(EdiParseException::class);
        new EdiParser()->parse($edi);
    }

    public function test_skips_error_line_that_otherwise_matches_pattern(): void
    {
        // Chybový řádek je jinak zcela validní (vyplněný čas i pole) a vyhověl by
        // regexu – bez detekce značky „ERROR" by se započítal jako platné QSO.
        $edi = "[REG1TEST;1]\nPCall=OK1ABC\n[QSORecords;2]\n"
            ."260315;0800;OK1XYZ;1;59;001;59;001;;JN79AB;3;;;;\n"
            ."260118;0909;ERROR;1;59;098;59;025;;JN79IW;0;;;;\n[END;]\n";

        $log = new EdiParser()->parse($edi);

        $this->assertSame(1, $log->qsoCount());
        $this->assertCount(1, $log->ignoredLines);
        $this->assertSame('OK1XYZ', $log->qsos[0]->callSign);
    }
}
