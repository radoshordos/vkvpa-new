<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cache ročních výsledků (Cache::flexible) s invalidací při přepočtu pořadí.
 */
class SchedulingAndCacheTest extends TestCase
{
    use RefreshDatabase;

    /** Pořadí vytvořeného kola v rámci testu – starts_at je v DB unikátní. */
    private int $koloSeq = 0;

    private function round(string $nazev, string $uzaverka): EdiRound
    {
        return EdiRound::create([
            'starts_at' => now()->subMonth()->subDays($this->koloSeq++)->toDateTimeString(),
            'closes_at' => $uzaverka,
            'name' => $nazev,
            'note' => '',
        ]);
    }

    public function test_yearly_results_are_cached_until_rank_round_invalidates(): void
    {
        $kat = EdiCategory::create(['name' => 'A', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);
        $kolo = $this->round('1. kolo 2026', now()->addDay()->toDateTimeString());

        $row = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1ABC',
            'locator' => 'JN99AJ', 'qso_count' => 10, 'qso_points' => 1, 'multiplier' => 100,
            'points' => 100, 'rank' => 1, 'approved' => true, 'sent' => false,
        ]);

        $scoring = app(ScoringService::class);

        $this->assertSame(100, (int) $scoring->yearlyResults(2026)->firstOrFail()->celkem);

        // Změna bodů „za zády" cache – bez přepočtu pořadí se musí vrátit cachovaná hodnota.
        $row->forceFill(['points' => 500])->save();
        $this->assertSame(100, (int) $scoring->yearlyResults(2026)->firstOrFail()->celkem);

        // rankRound zahodí cache roku → nová hodnota.
        $scoring->rankRound($kolo->id);
        $this->assertSame(500, (int) $scoring->yearlyResults(2026)->firstOrFail()->celkem);
    }
}
