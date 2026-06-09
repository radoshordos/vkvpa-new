<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Edi\EdiHeader;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiQso;
use App\Services\Edi\EdiValidator;
use Tests\TestCase;

/**
 * Kontrola kvality EDI deníku po importu.
 *
 * @see EdiValidator
 */
class EdiValidatorTest extends TestCase
{
    /**
     * @param  array{string,string,string,string}  ...$qsos  [date,time,callSign,receivedWwl]
     */
    private function log(int $declaredTotal = 0, array ...$qsos): EdiLog
    {
        $built = array_map(
            static fn (array $q): EdiQso => new EdiQso(
                date: $q[0], time: $q[1], callSign: $q[2], modeCode: '1',
                sentRst: '59', sentQsoNumber: '001', receivedRst: '59', receivedQsoNumber: '001',
                receivedExchange: '', receivedWwl: $q[3], qsoPoints: '1',
                newExchange: '', newWwl: '', newDxcc: '', duplicate: '',
            ),
            $qsos,
        );

        $header = new EdiHeader([
            'PCall' => 'OK1TEST', 'PWWLo' => 'JN99AJ', 'TDate' => '20260118;20260118',
            'PBand' => '144 MHz', 'PSect' => 'SINGLE', 'SPowe' => '100',
        ]);

        return new EdiLog($header, array_values($built), '', $declaredTotal ?: count($built));
    }

    public function test_clean_log_has_no_warnings(): void
    {
        $report = new EdiValidator()->validate($this->log(2,
            ['260118', '0830', 'OK1A', 'JN89AA'],
            ['260118', '0930', 'OK1B', 'JO70BB'],
        ));

        $this->assertFalse($report->hasWarnings());
        $this->assertSame([], $report->messages());
    }

    public function test_detects_duplicate_callsigns(): void
    {
        $report = new EdiValidator()->validate($this->log(0,
            ['260118', '0830', 'OK1A', 'JN89AA'],
            ['260118', '0930', 'OK1A', 'JN89AA'],
            ['260118', '1000', 'OK1A', 'JN89AA'],
        ));

        $this->assertSame(['OK1A' => 3], $report->duplicateCalls);
        $this->assertTrue($report->hasWarnings());
        $this->assertStringContainsString('OK1A (3×)', implode(' ', $report->messages()));
    }

    public function test_detects_invalid_locator(): void
    {
        $report = new EdiValidator()->validate($this->log(0,
            ['260118', '0830', 'OK1A', 'XX99ZZ'],   // ZZ není platný subčtverec
            ['260118', '0930', 'OK1B', 'JN89AA'],
        ));

        $this->assertSame(['OK1A: XX99ZZ'], $report->invalidLocators);
        $this->assertStringContainsString('Neplatný WWL', implode(' ', $report->messages()));
    }

    public function test_four_char_big_square_is_valid(): void
    {
        $report = new EdiValidator()->validate($this->log(0,
            ['260118', '0830', 'OK1A', 'JN89'],
        ));

        $this->assertSame([], $report->invalidLocators);
    }

    public function test_counts_empty_out_of_window_and_wrong_date(): void
    {
        $report = new EdiValidator()->validate($this->log(0,
            ['260118', '0830', 'OK1A', ''],         // prázdný WWL
            ['260118', '1230', 'OK1B', 'JN89AA'],   // mimo okno
            ['260117', '0900', 'OK1C', 'JN77AA'],   // jiný den
        ));

        $this->assertSame(1, $report->emptyLocators);
        $this->assertSame(1, $report->outOfWindow);
        $this->assertSame(1, $report->wrongDate);
    }

    public function test_flags_declared_vs_parsed_mismatch(): void
    {
        $report = new EdiValidator()->validate($this->log(5,
            ['260118', '0830', 'OK1A', 'JN89AA'],
        ));

        $this->assertStringContainsString('deklaruje 5 QSO', implode(' ', $report->messages()));
    }
}
