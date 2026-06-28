<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiRound;
use App\Support\ContestCalendar;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ContestRoundCommandsTest extends TestCase
{
    use RefreshDatabase;

    // ─── EnsureUpcomingRoundsCommand ───────────────────────────────────────────

    public function test_ensure_upcoming_creates_rounds_for_next_months(): void
    {
        $this->assertSame(0, EdiRound::count());

        Artisan::call('kola:ensure-upcoming', ['months' => 3]);

        $this->assertSame(3, EdiRound::count());
    }

    public function test_ensure_upcoming_skips_existing_rounds(): void
    {
        // Předem vytvoříme kolo pro aktuální měsíc.
        $now = now()->timezone('UTC');
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $start = ContestCalendar::roundStart($year, $month);
        EdiRound::create([
            'name' => 'Existující',
            'starts_at' => $start->toDateTimeString(),
            'closes_at' => ContestCalendar::uploadDeadline($start)->toDateTimeString(),
            'note' => '',
        ]);

        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $this->assertSame(1, EdiRound::count(), 'Žádné duplikáty pro existující kolo.');
    }

    public function test_ensure_upcoming_round_name_matches_pattern(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = EdiRound::first();
        $this->assertNotNull($kolo);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{4}$/', $kolo->name);
    }

    public function test_ensure_upcoming_sets_deadline_to_friday(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = EdiRound::first();
        $this->assertNotNull($kolo);
        $this->assertNotNull($kolo->closes_at);
        // Uzávěrka musí být pátek (dayOfWeek = 5).
        $this->assertSame(5, $kolo->closes_at->dayOfWeek);
        $this->assertSame('23:59:59', $kolo->closes_at->format('H:i:s'));
    }

    public function test_ensure_upcoming_sets_contest_start_time(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = EdiRound::first();
        $this->assertNotNull($kolo);
        // Start závodu = třetí neděle 08:00 UTC, uložený přímo v starts_at.
        $this->assertSame(0, $kolo->starts_at->dayOfWeek);
        $this->assertSame('08:00:00', $kolo->starts_at->format('H:i:s'));
    }

    // ─── Scheduler registrace ─────────────────────────────────────────────────

    public function test_ensure_upcoming_is_scheduled(): void
    {
        $events = Collection::make(app(Schedule::class)->events());

        $this->assertTrue(
            $events->contains(
                fn ($e): bool => str_contains((string) ($e->command ?? ''), 'kola:ensure-upcoming'),
            ),
            'Naplánovaná úloha kola:ensure-upcoming není registrovaná.',
        );
    }

    public function test_finalize_evaluated_is_scheduled(): void
    {
        $events = Collection::make(app(Schedule::class)->events());

        $this->assertTrue(
            $events->contains(
                fn ($e): bool => str_contains((string) ($e->command ?? ''), 'kola:finalize-evaluated'),
            ),
            'Naplánovaná úloha kola:finalize-evaluated není registrovaná.',
        );
    }
}
