<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cache ročních výsledků (Cache::flexible) s invalidací při přepočtu pořadí.
 */
class SchedulingAndCacheTest extends TestCase
{
    use RefreshDatabase;

    /** Pořadí vytvořeného kola v rámci testu – datum_konani je v DB unikátní. */
    private int $koloSeq = 0;

    private function kolo(string $nazev, string $uzaverka): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => now()->subMonth()->subDays($this->koloSeq++)->toDateTimeString(),
            'datum_uzaverky' => $uzaverka,
            'nazev' => $nazev,
            'poznamka' => '',
        ]);
    }

    public function test_yearly_results_are_cached_until_rank_round_invalidates(): void
    {
        $kat = EdiCategory::create(['name' => 'A', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);
        $kolo = $this->kolo('1. kolo 2026', now()->addDay()->toDateTimeString());

        $row = VkvpaData::create([
            'id_kola' => $kolo->id, 'id_kategorie' => $kat->id, 'znacka' => 'OK1ABC',
            'locator' => 'JN99AJ', 'pocet' => 10, 'bodu_za_qso' => 1, 'nasobice' => 100,
            'body' => 100, 'poradi' => 1, 'schvaleno' => true, 'odeslano' => false,
        ]);

        $scoring = app(ScoringService::class);

        $this->assertSame(100, (int) $scoring->yearlyResults(2026)->firstOrFail()->celkem);

        // Změna bodů „za zády" cache – bez přepočtu pořadí se musí vrátit cachovaná hodnota.
        $row->forceFill(['body' => 500])->save();
        $this->assertSame(100, (int) $scoring->yearlyResults(2026)->firstOrFail()->celkem);

        // rankRound zahodí cache roku → nová hodnota.
        $scoring->rankRound($kolo->id);
        $this->assertSame(500, (int) $scoring->yearlyResults(2026)->firstOrFail()->celkem);
    }
}
