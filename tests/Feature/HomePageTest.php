<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Prispevek;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Úvodní stránka reaguje na životní cyklus kola: podstav „závod právě
 * probíhá", počítadlo přijatých hlášení, karta posledního vyhodnoceného
 * kola a sekce „Z diskuse".
 */
class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attrs */
    private function kolo(array $attrs = []): VkvpaKola
    {
        return VkvpaKola::create(array_merge([
            'nazev' => '06/2026',
            'poznamka' => '',
            'aktivni' => false,
            'vyhodnoceno' => null,
            'datum_konani' => '2026-06-21',
            'datum_uzaverky' => '2026-06-26 23:59:59',
        ], $attrs));
    }

    private function zaznam(VkvpaKola $kolo, string $znacka): VkvpaData
    {
        return VkvpaData::create([
            'id_kola' => $kolo->id,
            'id_kategorie' => VkvpaKategorie::firstOrCreate(
                ['nazev' => '144 MHz SO'],
                ['popis' => '', 'zkratka' => '144SO', 'dxid' => 0],
            )->id,
            'znacka' => $znacka,
            'locator' => 'JN79XX',
        ]);
    }

    // ------------------------------------------------------------------
    // Podstav „závod právě probíhá" (08:00–11:00 UTC v den závodu)

    public function test_running_substate_inside_contest_window(): void
    {
        Carbon::setTestNow('2026-06-21 09:00:00');
        $this->kolo(['aktivni' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Závod právě probíhá')
            ->assertSee('do konce závodu');
    }

    public function test_active_state_after_contest_window(): void
    {
        Carbon::setTestNow('2026-06-21 12:00:00');
        $this->kolo(['aktivni' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Aktuální kolo – přijímáme hlášení')
            ->assertSee('do uzávěrky hlášení')
            ->assertDontSee('Závod právě probíhá');
    }

    public function test_running_substate_even_when_not_yet_activated(): void
    {
        // Admin/cron kolo ještě neaktivoval, ale závodní okno běží
        // (stav Prijem) – úvodka přesto ukáže „závod právě probíhá".
        Carbon::setTestNow('2026-06-21 09:00:00');
        $this->kolo(['aktivni' => false]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Závod právě probíhá');
    }

    // ------------------------------------------------------------------
    // Počítadlo přijatých hlášení

    public function test_received_counter_with_entries(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $kolo = $this->kolo(['aktivni' => true]);
        $this->zaznam($kolo, 'OK1AAA');
        $this->zaznam($kolo, 'OK1BBB');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Zatím přijata 2 hlášení');
    }

    public function test_received_counter_without_entries(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->kolo(['aktivni' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Zatím nebylo přijato žádné hlášení — buďte první!');
    }

    // ------------------------------------------------------------------
    // Karta posledního vyhodnoceného kola

    public function test_last_evaluated_card_shown_with_upcoming_round(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->kolo(['nazev' => '05/2026', 'datum_konani' => '2026-05-17', 'datum_uzaverky' => '2026-05-22 23:59:59', 'vyhodnoceno' => '2026-05-25 10:00:00']);
        $this->kolo(['nazev' => '06/2026']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Nadcházející kolo')
            ->assertSee('Poslední vyhodnocené kolo')
            ->assertSee('05/2026');
    }

    public function test_last_evaluated_card_hidden_when_hero_not_upcoming(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->kolo(['nazev' => '05/2026', 'datum_konani' => '2026-05-17', 'datum_uzaverky' => '2026-05-22 23:59:59', 'vyhodnoceno' => '2026-05-25 10:00:00']);
        $this->kolo(['aktivni' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Poslední vyhodnocené kolo');
    }

    // ------------------------------------------------------------------
    // Diskuse na úvodce

    public function test_hero_links_to_round_discussion(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $kolo = $this->kolo(['aktivni' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('diskuse.show', $kolo->id));
    }

    public function test_discussion_section_shows_latest_posts(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $kolo = $this->kolo(['aktivni' => true]);
        Prispevek::create(['kolo_id' => $kolo->id, 'znacka' => 'OK1XYZ', 'text' => 'Pěkné podmínky dnes ráno!', 'ip' => '127.0.0.1']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Z diskuse')
            ->assertSee('OK1XYZ')
            ->assertSee('Pěkné podmínky dnes ráno!');
    }

    public function test_discussion_section_hidden_without_posts(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->kolo(['aktivni' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Z diskuse');
    }
}
