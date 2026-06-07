<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use App\Services\Scoring\ScoringService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Nové provozní funkce: automatická deaktivace prošlých kol (scheduler)
 * a cache ročních výsledků (Cache::flexible) s invalidací při přepočtu pořadí.
 */
class SchedulingAndCacheTest extends TestCase
{
    use RefreshDatabase;

    private function kolo(string $nazev, string $uzaverka, bool $aktivni = true): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => now()->subMonth(),
            'datum_uzaverky' => $uzaverka,
            'nazev' => $nazev,
            'poznamka' => '',
            'aktivni' => $aktivni,
        ]);
    }

    public function test_deactivate_expired_rounds_deactivates_only_past_active_rounds(): void
    {
        $proslé = $this->kolo('1. kolo 2026', now()->subDay()->toDateTimeString());
        $budoucí = $this->kolo('2. kolo 2026', now()->addDay()->toDateTimeString());
        $jižNeaktivní = $this->kolo('3. kolo 2026', now()->subDay()->toDateTimeString(), aktivni: false);

        $pocet = app(ScoringService::class)->deactivateExpiredRounds();

        $this->assertSame(1, $pocet);
        $this->assertFalse($proslé->refresh()->aktivni);
        $this->assertTrue($budoucí->refresh()->aktivni);
        $this->assertFalse($jižNeaktivní->refresh()->aktivni);
    }

    public function test_scheduled_task_is_registered(): void
    {
        $events = Collection::make(app(Schedule::class)->events());

        $this->assertTrue(
            $events->contains(
                fn ($event): bool => str_contains((string) ($event->command ?? ''), 'kola:deactivate-expired'),
            ),
            'Naplánovaná úloha kola:deactivate-expired není registrovaná.',
        );
    }

    public function test_yearly_results_are_cached_until_rank_round_invalidates(): void
    {
        $kat = VkvpaKategorie::create(['nazev' => 'A', 'popis' => '', 'zkratka' => 'A', 'dxid' => 0]);
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
