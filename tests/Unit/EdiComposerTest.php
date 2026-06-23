<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Edi\EdiComposer;
use App\Services\Edi\EdiParser;
use Tests\TestCase;

/**
 * Skladač EDI deníku (opak parseru) – ověřujeme, že složený text projde
 * zpětnou validací {@see EdiParser} a nese správné hodnoty.
 *
 * @see EdiComposer
 */
class EdiComposerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function header(): array
    {
        return [
            'tname' => 'Provozni aktiv',
            'tdate' => '2026-03-15',
            'pcall' => 'ok2kjt',
            'pwwlo' => 'jn99aj',
            'psect' => 'SINGLE',
            'pband' => '144 MHz',
            'rname' => 'Pavel',
            'rphon' => '602533338',
            'rhbbs' => 'ok2kjt@example.com',
            'spowe' => '800',
            'stxeq' => 'IC7600',
            'sante' => '10 el',
            'remarks' => 'pozdrav',
        ];
    }

    public function test_composed_log_parses_back(): void
    {
        $composer = new EdiComposer;

        $text = $composer->compose($this->header(), [
            ['time' => '0800', 'call' => 'OK2IMH', 'mode' => 1, 'rst_s' => '59', 'rst_r' => '59', 'wwl' => 'JN99BP'],
            ['time' => '0801', 'call' => 'ok2iwu', 'mode' => 2, 'rst_s' => '599', 'rst_r' => '599', 'wwl' => 'JN89PV'],
            // Nekompletní řádek (bez lokátoru) se vynechá a nezapočítá do počtu.
            ['time' => '0802', 'call' => 'OK1XYZ', 'mode' => 1, 'rst_s' => '59', 'rst_r' => '59', 'wwl' => ''],
        ]);

        $log = new EdiParser()->parse($text);

        $this->assertSame('OK2KJT', $log->header->pCall());
        $this->assertSame('JN99AJ', $log->header->pWWLo());
        $this->assertSame('144 MHz', $log->header->pBand());
        $this->assertSame(800, $log->header->sPowe());
        $this->assertSame('20260315;20260315', $log->header->tDate());

        // Jen 2 kompletní spojení; deklarovaný i naparsovaný počet sedí.
        $this->assertSame(2, $log->declaredTotal);
        $this->assertCount(2, $log->qsos);
        $this->assertSame('260315', $log->qsos[0]->date);
        $this->assertSame('OK2IMH', $log->qsos[0]->callSign);
        $this->assertSame('JN99BP', $log->qsos[0]->receivedWwl);
    }

    public function test_empty_log_has_zero_records(): void
    {
        $composer = new EdiComposer;

        $text = $composer->compose($this->header(), []);

        $this->assertStringContainsString('[QSORecords;0]', $text);
        $this->assertStringContainsString('[END;]', $text);
    }
}
