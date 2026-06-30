<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DiscussionPost;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Úvodní stránka reaguje na životní cyklus kola: stav „závod právě
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
    private function round(array $attrs = []): EdiRound
    {
        return EdiRound::create(array_merge([
            'name' => '06/2026',
            'note' => '',
            'evaluated_at' => null,
            'starts_at' => '2026-06-21 08:00:00',
            'closes_at' => '2026-06-26 23:59:59',
        ], $attrs));
    }

    private function zaznam(EdiRound $kolo, string $znacka): EdiEntry
    {
        return EdiEntry::create([
            'round_id' => $kolo->id,
            'category_id' => EdiCategory::firstOrCreate(
                ['section' => 'SO', 'variant' => 'domestic'],
                ['name' => '144 MHz SO'],
            )->id,
            'callsign' => $znacka,
            'locator' => 'JN79XX',
        ]);
    }

    // ------------------------------------------------------------------
    // Stav „závod právě probíhá" (08:00–11:00 UTC v den závodu)

    public function test_running_state_inside_contest_window(): void
    {
        Carbon::setTestNow('2026-06-21 09:00:00');
        $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Závod právě probíhá')
            ->assertSee('do konce závodu');
    }

    public function test_deadline_state_after_contest_window(): void
    {
        Carbon::setTestNow('2026-06-21 12:00:00');
        $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Probíhá příjem hlášení')
            ->assertSee('do uzávěrky hlášení')
            ->assertDontSee('Závod právě probíhá');
    }

    // ------------------------------------------------------------------
    // Počítadlo přijatých hlášení

    public function test_received_counter_with_entries(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $kolo = $this->round();
        $this->zaznam($kolo, 'OK1AAA');
        $this->zaznam($kolo, 'OK1BBB');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Zatím přijata 2 hlášení');
    }

    public function test_received_counter_without_entries(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Zatím nebylo přijato žádné hlášení — buďte první!');
    }

    // ------------------------------------------------------------------
    // Karta posledního vyhodnoceného kola

    public function test_last_evaluated_card_shown_with_upcoming_round(): void
    {
        Carbon::setTestNow('2026-06-01 12:00:00');
        $this->round(['name' => '05/2026', 'starts_at' => '2026-05-17 08:00:00', 'closes_at' => '2026-05-22 23:59:59', 'evaluated_at' => '2026-05-25 10:00:00']);
        $this->round(['name' => '06/2026']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Nadcházející kolo')
            ->assertSee('Poslední vyhodnocené kolo')
            ->assertSee('05/2026');
    }

    public function test_last_evaluated_card_hidden_when_hero_not_upcoming(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->round(['name' => '05/2026', 'starts_at' => '2026-05-17 08:00:00', 'closes_at' => '2026-05-22 23:59:59', 'evaluated_at' => '2026-05-25 10:00:00']);
        $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Poslední vyhodnocené kolo');
    }

    // ------------------------------------------------------------------
    // Diskuse na úvodce

    public function test_hero_links_to_round_discussion(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $kolo = $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('diskuse.show', $kolo->id));
    }

    public function test_discussion_section_shows_latest_posts(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $kolo = $this->round();
        DiscussionPost::create(['round_id' => $kolo->id, 'callsign' => 'OK1XYZ', 'body' => 'Pěkné podmínky dnes ráno!']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Z diskuse')
            ->assertSee('OK1XYZ')
            ->assertSee('Pěkné podmínky dnes ráno!');
    }

    public function test_discussion_section_hidden_without_posts(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Z diskuse');
    }

    public function test_home_links_to_long_term_trends(): void
    {
        Carbon::setTestNow('2026-06-22 12:00:00');
        $this->round();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('statistiky.trendy', [], false), false)
            ->assertSee(__('pages.home.section_stats'))
            ->assertSee(__('pages.home.ql_trends'));
    }
}
