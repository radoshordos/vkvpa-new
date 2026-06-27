<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\FinalizeEvaluatedRoundsCommand;
use App\Models\EdiCategory;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Automatické vyhodnocení kola po uzávěrce: všechny záznamy převzaty, nebo
 * uplynula 20denní záchranná lhůta od `datum_uzaverky`.
 *
 * @see FinalizeEvaluatedRoundsCommand
 * @see VkvpaKola::maBytVyhodnoceno()
 */
class FinalizeEvaluatedRoundsCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @param array<string, mixed> $attrs */
    private function kolo(array $attrs): VkvpaKola
    {
        return VkvpaKola::create(array_merge([
            'nazev' => '05/2026',
            'poznamka' => '',
        ], $attrs));
    }

    private function zaznam(VkvpaKola $kolo, bool $schvaleno, int $body = 50): VkvpaData
    {
        $kat = EdiCategory::firstOrCreate(
            ['band' => 'A', 'section' => 'SO', 'variant' => 'domestic'],
            ['name' => '144 MHz single op'],
        );

        return VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id,
            'znacka' => 'OK1T'.(++$this->seq), 'locator' => 'JN99AJ',
            'pocet' => 10, 'nasobice' => 5, 'body' => $body, 'bodu_za_qso' => 0,
            'schvaleno' => $schvaleno, 'odeslano' => false,
        ]);
    }

    private function runFinalize(): void
    {
        $this->assertSame(0, Artisan::call('kola:finalize-evaluated'));
    }

    public function test_finalizes_round_when_all_records_taken_over_after_deadline(): void
    {
        $kolo = $this->kolo(['datum_konani' => now()->subDays(7), 'datum_uzaverky' => now()->subDay()]);
        $this->zaznam($kolo, schvaleno: true, body: 100);
        $this->zaznam($kolo, schvaleno: true, body: 50);

        $this->runFinalize();

        $kolo->refresh();
        $this->assertNotNull($kolo->vyhodnoceno);
        // Vyhodnocení přepočítá i pořadí.
        $this->assertSame(1, $kolo->hlaseni()->where('body', 100)->value('poradi'));
        $this->assertSame(2, $kolo->hlaseni()->where('body', 50)->value('poradi'));
    }

    public function test_finalizes_via_fallback_after_20_days_even_with_unapproved(): void
    {
        // Uzávěrka před 21 dny – 20denní lhůta uplynula, ač není vše převzato.
        $kolo = $this->kolo(['datum_konani' => now()->subDays(27), 'datum_uzaverky' => now()->subDays(21)]);
        $this->zaznam($kolo, schvaleno: false);

        $this->runFinalize();

        $this->assertNotNull($kolo->refresh()->vyhodnoceno);
    }

    public function test_finalizes_empty_round_after_deadline(): void
    {
        // Prázdné kolo po uzávěrce je vakuózně „celé převzaté".
        $kolo = $this->kolo(['datum_konani' => now()->subDays(7), 'datum_uzaverky' => now()->subDay()]);

        $this->runFinalize();

        $this->assertNotNull($kolo->refresh()->vyhodnoceno);
    }

    public function test_does_not_finalize_while_reception_open(): void
    {
        // Příjem ještě běží (uzávěrka v budoucnu) – nevyhodnocovat, i když je vše převzato.
        $kolo = $this->kolo(['datum_konani' => now()->subDays(2), 'datum_uzaverky' => now()->addDays(3)]);
        $this->zaznam($kolo, schvaleno: true);

        $this->runFinalize();

        $this->assertNull($kolo->refresh()->vyhodnoceno);
    }

    public function test_does_not_finalize_when_unapproved_within_fallback_window(): void
    {
        // Po uzávěrce, ale lhůta zdaleka neuplynula a jeden záznam je nepřevzatý.
        $kolo = $this->kolo(['datum_konani' => now()->subDays(7), 'datum_uzaverky' => now()->subDays(2)]);
        $this->zaznam($kolo, schvaleno: true);
        $this->zaznam($kolo, schvaleno: false);

        $this->runFinalize();

        $this->assertNull($kolo->refresh()->vyhodnoceno);
    }

    public function test_leaves_already_evaluated_round_untouched(): void
    {
        $stamp = now()->subDays(3);
        $kolo = $this->kolo([
            'datum_konani' => now()->subDays(10), 'datum_uzaverky' => now()->subDays(5),
            'vyhodnoceno' => $stamp,
        ]);

        $this->runFinalize();

        $this->assertSame(
            $stamp->toDateTimeString(),
            $kolo->refresh()->vyhodnoceno?->toDateTimeString(),
        );
    }
}
