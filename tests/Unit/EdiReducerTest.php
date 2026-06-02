<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Edi\EdiReducer;
use PHPUnit\Framework\TestCase;

/**
 * Ořez EDI na závodní okno 08:00–11:00 UTC (podklad pro akci EDIR).
 *
 * @see \App\Services\Edi\EdiReducer
 */
class EdiReducerTest extends TestCase
{
    public function test_reduce_keeps_only_qso_inside_window_and_recounts(): void
    {
        $raw = implode("\n", [
            '[REG1TEST;1]',
            'PCall=OK2KJT',
            '[Remarks]',
            'poznamka',
            '[QSORecords;4]',
            '260315;0759;OK1A;1;59;001;59;001;;JN99BP;2;;;;', // 07:59 → před oknem (pryč)
            '260315;0800;OK1B;1;59;002;59;002;;JN99BP;2;;;;', // 08:00 → hranice (zůstává)
            '260315;1100;OK1C;1;59;003;59;003;;JN99BP;2;;;;', // 11:00 → hranice (zůstává)
            '260315;1101;OK1D;1;59;004;59;004;;JN99BP;2;;;;', // 11:01 → po okně (pryč)
            '[END;]',
        ]) . "\n";

        $out = (new EdiReducer())->reduce($raw);

        // Zůstávají jen QSO v okně a počet je přepočítán.
        $this->assertStringContainsString('[QSORecords;2]', $out);
        $this->assertStringContainsString('OK1B', $out);
        $this->assertStringContainsString('OK1C', $out);
        $this->assertStringNotContainsString('OK1A', $out);
        $this->assertStringNotContainsString('OK1D', $out);

        // Hlavička, [Remarks] i [END] zůstávají zachované.
        $this->assertStringContainsString('PCall=OK2KJT', $out);
        $this->assertStringContainsString('[Remarks]', $out);
        $this->assertStringContainsString('poznamka', $out);
        $this->assertStringContainsString('[END;]', $out);
    }
}
