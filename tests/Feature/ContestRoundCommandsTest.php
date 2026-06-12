<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\VkvpaKola;
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
        $this->assertSame(0, VkvpaKola::count());

        Artisan::call('kola:ensure-upcoming', ['months' => 3]);

        $this->assertSame(3, VkvpaKola::count());
    }

    public function test_ensure_upcoming_skips_existing_rounds(): void
    {
        // Předem vytvoříme kolo pro aktuální měsíc.
        $now = now()->timezone('UTC');
        $year = (int) $now->format('Y');
        $month = (int) $now->format('n');

        $start = ContestCalendar::roundStart($year, $month);
        VkvpaKola::create([
            'nazev' => 'Existující',
            'datum_konani' => $start->toDateTimeString(),
            'datum_uzaverky' => ContestCalendar::uploadDeadline($start)->toDateTimeString(),
            'poznamka' => '',
        ]);

        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $this->assertSame(1, VkvpaKola::count(), 'Žádné duplikáty pro existující kolo.');
    }

    public function test_ensure_upcoming_round_name_matches_pattern(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = VkvpaKola::first();
        $this->assertNotNull($kolo);
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{4}$/', $kolo->nazev);
    }

    public function test_ensure_upcoming_sets_deadline_to_friday(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = VkvpaKola::first();
        $this->assertNotNull($kolo);
        $this->assertNotNull($kolo->datum_uzaverky);
        // Uzávěrka musí být pátek (dayOfWeek = 5).
        $this->assertSame(5, $kolo->datum_uzaverky->dayOfWeek);
        $this->assertSame('23:59:59', $kolo->datum_uzaverky->format('H:i:s'));
    }

    public function test_ensure_upcoming_sets_contest_start_time(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = VkvpaKola::first();
        $this->assertNotNull($kolo);
        // Start závodu = třetí neděle 08:00 UTC, uložený přímo v datum_konani.
        $this->assertSame(0, $kolo->datum_konani->dayOfWeek);
        $this->assertSame('08:00:00', $kolo->datum_konani->format('H:i:s'));
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
}
