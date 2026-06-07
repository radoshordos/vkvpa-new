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
            'datum_konani' => $start->toDateString(),
            'datum_uzaverky' => ContestCalendar::uploadDeadline($start)->toDateTimeString(),
            'aktivni' => false,
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
        $this->assertMatchesRegularExpression('/^VKV PA \d{2}\/\d{4}$/', $kolo->nazev);
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

    public function test_ensure_upcoming_creates_inactive_rounds(): void
    {
        Artisan::call('kola:ensure-upcoming', ['months' => 1]);

        $kolo = VkvpaKola::first();
        $this->assertNotNull($kolo);
        $this->assertFalse($kolo->aktivni);
    }

    // ─── ActivateDueRoundsCommand ──────────────────────────────────────────────

    public function test_activate_due_activates_past_round_with_future_deadline(): void
    {
        // Datum závodu v minulosti (= 08:00 UTC v ten den je jistě za námi),
        // uzávěrka v budoucnosti.
        $kolo = VkvpaKola::create([
            'nazev' => 'VKV PA minulost',
            'datum_konani' => now()->subDays(3)->toDateString(),
            'datum_uzaverky' => now()->addDays(3)->toDateTimeString(),
            'aktivni' => false,
            'poznamka' => '',
        ]);

        Artisan::call('kola:activate-due');

        $this->assertTrue($kolo->refresh()->aktivni);
    }

    public function test_activate_due_does_not_activate_future_round(): void
    {
        // Datum závodu v budoucnosti – ještě nenastal.
        $kolo = VkvpaKola::create([
            'nazev' => 'VKV PA budoucnost',
            'datum_konani' => now()->addDays(7)->toDateString(),
            'datum_uzaverky' => now()->addDays(12)->toDateTimeString(),
            'aktivni' => false,
            'poznamka' => '',
        ]);

        Artisan::call('kola:activate-due');

        $this->assertFalse($kolo->refresh()->aktivni);
    }

    public function test_activate_due_ignores_already_expired_rounds(): void
    {
        // Datum závodu v minulosti, ale uzávěrka také v minulosti.
        $kolo = VkvpaKola::create([
            'nazev' => 'VKV PA minulé kolo',
            'datum_konani' => now()->subDays(10)->toDateString(),
            'datum_uzaverky' => now()->subDays(5)->toDateTimeString(),
            'aktivni' => false,
            'poznamka' => '',
        ]);

        Artisan::call('kola:activate-due');

        $this->assertFalse($kolo->refresh()->aktivni);
    }

    public function test_activate_due_ignores_already_active_rounds(): void
    {
        $kolo = VkvpaKola::create([
            'nazev' => 'VKV PA aktivní',
            'datum_konani' => now()->subDays(3)->toDateString(),
            'datum_uzaverky' => now()->addDays(3)->toDateTimeString(),
            'aktivni' => true,
            'poznamka' => '',
        ]);

        Artisan::call('kola:activate-due');

        // Aktivni zůstane true (nezmění se).
        $this->assertTrue($kolo->refresh()->aktivni);
    }

    // ─── Scheduler registrace ─────────────────────────────────────────────────

    public function test_activate_due_is_scheduled(): void
    {
        $events = Collection::make(app(Schedule::class)->events());

        $this->assertTrue(
            $events->contains(
                fn ($e): bool => str_contains((string) ($e->command ?? ''), 'kola:activate-due'),
            ),
            'Naplánovaná úloha kola:activate-due není registrovaná.',
        );
    }

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
