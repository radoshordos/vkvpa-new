<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiController;
use App\Models\Edihead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zobrazení EDI deníku: akce EDI (původní) a EDIR (redukovaný na 08–11 UTC).
 *
 * @see EdiController
 */
class EdiZobrazeniTest extends TestCase
{
    use RefreshDatabase;

    private function denik(): Edihead
    {
        $raw = implode("\n", [
            '[REG1TEST;1]',
            'PCall=OK2KJT',
            '[QSORecords;2]',
            '260315;0801;OK1A;1;59;001;59;001;;JN99BP;2;;;;', // 08:01 → v okně
            '260315;1200;OK1Z;1;59;002;59;002;;JN99BP;2;;;;', // 12:00 → mimo okno
            '[END;]',
        ])."\n";

        return Edihead::create([
            'TDate' => '20260315;20260315', 'PCall' => 'OK2KJT', 'PWWLo' => 'JN99AJ',
            'PSect' => '', 'PBand' => '', 'RName' => 'X', 'RPhon' => '', 'RHBBS' => '',
            'SPowe' => 100, 'src' => $raw,
        ]);
    }

    public function test_shows_original_edi(): void
    {
        $head = $this->denik();

        $this->get(route('edi.soubor', ['head' => $head->ID]))
            ->assertOk()
            ->assertSee('OK1A')
            ->assertSee('OK1Z')            // původní obsahuje i QSO mimo okno
            ->assertSee('[QSORecords;2]');
    }

    public function test_shows_reduced_edi(): void
    {
        $head = $this->denik();

        $this->get(route('edi.soubor.redukovany', ['head' => $head->ID]))
            ->assertOk()
            ->assertSee('OK1A')             // v okně → zůstává
            ->assertDontSee('OK1Z')         // 12:00 → oříznuto
            ->assertSee('[QSORecords;1]');  // přepočítaný počet
    }

    public function test_missing_src_returns_404(): void
    {
        $head = $this->denik();
        $head->update(['src' => null]);

        $this->get(route('edi.soubor', ['head' => $head->ID]))->assertNotFound();
    }
}
