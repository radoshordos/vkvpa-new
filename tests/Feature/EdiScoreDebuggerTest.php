<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Edi\EdiHeader;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiLog;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EdiQso;
use App\Services\Scoring\EdiScoreDebugger;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Debug rozpad bodování EDI deníku.
 *
 * @see EdiScoreDebugger
 */
class EdiScoreDebuggerTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array{string,string,string,string,string}  ...$qsos  [date,time,callSign,receivedWwl,duplicate] */
    private function log(array ...$qsos): EdiLog
    {
        $built = array_map(
            static fn (array $q): EdiQso => new EdiQso(
                date: $q[0], time: $q[1], callSign: $q[2], modeCode: '1',
                sentRst: '59', sentQsoNumber: '001', receivedRst: '59', receivedQsoNumber: '001',
                receivedExchange: '', receivedWwl: $q[3], qsoPoints: '1',
                newExchange: '', newWwl: '', newDxcc: '', duplicate: $q[4],
            ),
            $qsos,
        );

        $header = new EdiHeader([
            'PCall' => 'OK1TEST', 'PWWLo' => 'JN99AJ', 'TDate' => '20260118;20260118',
            'PBand' => '144 MHz', 'PSect' => 'SINGLE', 'SPowe' => '100',
        ]);

        return new EdiLog($header, array_values($built), '', count($built));
    }

    public function test_classifies_each_qso_line(): void
    {
        $report = new EdiScoreDebugger()->analyze($this->log(
            ['260118', '0830', 'OK1A', 'JN89AA', ''],   // započteno, nový násobič
            ['260118', '0930', 'OK1B', 'JN89BB', ''],   // započteno, stejný čtverec JN89
            ['260118', '1000', 'OK1C', 'JO70AA', ''],   // započteno, nový násobič
            ['260118', '1230', 'OK1D', 'JN88AA', ''],   // mimo okno (12:30)
            ['260117', '0900', 'OK1E', 'JN77AA', ''],   // jiný den (17.)
            ['260118', '0900', 'OK1F', 'JN99XX', ''],   // vlastní čtverec JN99 → započteno
            ['260118', '0945', 'OK1G', 'JN89AA', 'D'],  // započteno + duplikát
        ));

        // pocet=5 (A,B,C,F,G). Body přepočítané z lokátorů (domácí JN99):
        // JN89=3, JN89=3, JO70=4, vlastní JN99=2, JN89 dup=3 → boduZaQso=15.
        // cizí čtverce {JN89,JO70} + vlastní JN99 → nasobice=3, body=15×3=45.
        $this->assertSame(5, $report->pocet);
        $this->assertSame(15, $report->boduZaQso);
        $this->assertSame(3, $report->nasobice);
        $this->assertSame(45, $report->body);

        $this->assertSame(1, $report->excludedOutOfWindow);
        $this->assertSame(1, $report->excludedWrongDate);
        $this->assertSame(1, $report->ownSquareCount);     // vlastní čtverec – započteno
        $this->assertSame(1, $report->duplicateCount);

        // Body za spojení na řádcích jsou přepočítané z lokátorů.
        $this->assertSame(3, $report->rows[0]->points);   // JN89 = soused
        $this->assertSame(4, $report->rows[2]->points);   // JO70 = 2 pásy
        $this->assertSame(2, $report->rows[5]->points);   // vlastní JN99

        // Důvody jednotlivých řádků.
        $this->assertSame('counted', $report->rows[0]->reason);
        $this->assertTrue($report->rows[0]->newMultiplier);
        $this->assertSame('counted', $report->rows[1]->reason);
        $this->assertFalse($report->rows[1]->newMultiplier);   // stejný čtverec
        $this->assertSame('out_of_window', $report->rows[3]->reason);
        $this->assertSame('wrong_date', $report->rows[4]->reason);
        $this->assertSame('counted', $report->rows[5]->reason);     // vlastní čtverec se počítá
        $this->assertTrue($report->rows[5]->isOwnSquare);
        $this->assertFalse($report->rows[5]->newMultiplier);        // vlastní není nový cizí násobič
        $this->assertTrue($report->rows[6]->duplicate);
        $this->assertTrue($report->rows[6]->counted);          // duplikát se stále počítá
    }

    public function test_matches_scoreedi_on_fixture(): void
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');
        $log = new EdiParser()->parse($edi);

        $report = new EdiScoreDebugger()->analyze($log);

        // Debug rozpad musí dát stejné skóre jako ostrý výpočet přes DB.
        $head = new EdiImportService()->import($log);
        $score = app(ScoringService::class)->scoreEdi($head);

        $this->assertSame($score->pocet, $report->pocet);
        $this->assertSame($score->boduZaQso, $report->boduZaQso);
        $this->assertSame($score->nasobice, $report->nasobice);
        $this->assertSame($score->body, $report->body);
    }
}
