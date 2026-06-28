<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\FinalizeEvaluatedRoundsCommand;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Automatické vyhodnocení kola po uzávěrce: všechny záznamy převzaty, nebo
 * uplynula 20denní záchranná lhůta od `closes_at`.
 *
 * @see FinalizeEvaluatedRoundsCommand
 * @see EdiRound::maBytVyhodnoceno()
 */
class FinalizeEvaluatedRoundsCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @param array<string, mixed> $attrs */
    private function round(array $attrs): EdiRound
    {
        return EdiRound::create(array_merge([
            'name' => '05/2026',
            'note' => '',
        ], $attrs));
    }

    private function zaznam(EdiRound $kolo, bool $approved, int $body = 50): EdiEntry
    {
        $kat = EdiCategory::firstOrCreate(
            ['section' => 'SO', 'variant' => 'domestic'],
            ['name' => '144 MHz single op'],
        );

        return EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id,
            'callsign' => 'OK1T'.(++$this->seq), 'locator' => 'JN99AJ',
            'qso_count' => 10, 'multiplier' => 5, 'points' => $body, 'qso_points' => 0,
            'approved' => $approved, 'sent' => false,
        ]);
    }

    private function runFinalize(): void
    {
        $this->assertSame(0, Artisan::call('kola:finalize-evaluated'));
    }

    public function test_finalizes_round_when_all_records_taken_over_after_deadline(): void
    {
        $kolo = $this->round(['starts_at' => now()->subDays(7), 'closes_at' => now()->subDay()]);
        $this->zaznam($kolo, approved: true, body: 100);
        $this->zaznam($kolo, approved: true, body: 50);

        $this->runFinalize();

        $kolo->refresh();
        $this->assertNotNull($kolo->evaluated_at);
        // Vyhodnocení přepočítá i pořadí.
        $this->assertSame(1, $kolo->entries()->where('points', 100)->value('rank'));
        $this->assertSame(2, $kolo->entries()->where('points', 50)->value('rank'));
    }

    public function test_finalizes_via_fallback_after_20_days_even_with_unapproved(): void
    {
        // Uzávěrka před 21 dny – 20denní lhůta uplynula, ač není vše převzato.
        $kolo = $this->round(['starts_at' => now()->subDays(27), 'closes_at' => now()->subDays(21)]);
        $this->zaznam($kolo, approved: false);

        $this->runFinalize();

        $this->assertNotNull($kolo->refresh()->evaluated_at);
    }

    public function test_finalizes_empty_round_after_deadline(): void
    {
        // Prázdné kolo po uzávěrce je vakuózně „celé převzaté".
        $kolo = $this->round(['starts_at' => now()->subDays(7), 'closes_at' => now()->subDay()]);

        $this->runFinalize();

        $this->assertNotNull($kolo->refresh()->evaluated_at);
    }

    public function test_does_not_finalize_while_reception_open(): void
    {
        // Příjem ještě běží (uzávěrka v budoucnu) – nevyhodnocovat, i když je vše převzato.
        $kolo = $this->round(['starts_at' => now()->subDays(2), 'closes_at' => now()->addDays(3)]);
        $this->zaznam($kolo, approved: true);

        $this->runFinalize();

        $this->assertNull($kolo->refresh()->evaluated_at);
    }

    public function test_does_not_finalize_when_unapproved_within_fallback_window(): void
    {
        // Po uzávěrce, ale lhůta zdaleka neuplynula a jeden záznam je nepřevzatý.
        $kolo = $this->round(['starts_at' => now()->subDays(7), 'closes_at' => now()->subDays(2)]);
        $this->zaznam($kolo, approved: true);
        $this->zaznam($kolo, approved: false);

        $this->runFinalize();

        $this->assertNull($kolo->refresh()->evaluated_at);
    }

    public function test_leaves_already_evaluated_round_untouched(): void
    {
        $stamp = now()->subDays(3);
        $kolo = $this->round([
            'starts_at' => now()->subDays(10), 'closes_at' => now()->subDays(5),
            'evaluated_at' => $stamp,
        ]);

        $this->runFinalize();

        $this->assertSame(
            $stamp->toDateTimeString(),
            $kolo->refresh()->evaluated_at?->toDateTimeString(),
        );
    }
}
