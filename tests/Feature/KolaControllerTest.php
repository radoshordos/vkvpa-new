<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\KolaController;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Veřejný výpis kol závodu.
 *
 * @see KolaController
 */
class KolaControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_kola_page_loads_for_guest(): void
    {
        $this->get(route('kola.index'))->assertOk();
    }

    public function test_kola_page_shows_all_rounds_ordered_newest_first(): void
    {
        VkvpaKola::create(['datum_konani' => '2025-01-18', 'datum_uzaverky' => '2025-02-01', 'nazev' => '01/2025', 'poznamka' => '']);
        VkvpaKola::create(['datum_konani' => '2026-01-17', 'datum_uzaverky' => '2026-02-01', 'nazev' => '01/2026', 'poznamka' => '']);

        $response = $this->get(route('kola.index'))->assertOk();

        $pos2025 = strpos((string) $response->content(), '01/2025');
        $pos2026 = strpos((string) $response->content(), '01/2026');
        $this->assertNotFalse($pos2025);
        $this->assertNotFalse($pos2026);
        // Novější kolo (2026) musí být výše než starší (2025).
        $this->assertLessThan($pos2025, $pos2026);
    }

    public function test_kola_page_shows_empty_state_without_rounds(): void
    {
        $this->get(route('kola.index'))
            ->assertOk()
            ->assertSee('Kola závodu');
    }

    public function test_ical_feed_returns_calendar_content_type(): void
    {
        $this->get(route('kola.ical'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertSee('BEGIN:VCALENDAR', false)
            ->assertSee('END:VCALENDAR', false);
    }

    public function test_ical_feed_contains_event_for_round_in_contest_window(): void
    {
        VkvpaKola::create([
            'datum_konani' => '2026-01-17',
            'datum_uzaverky' => '2026-02-01 23:59:00',
            'nazev' => '01/2026',
            'poznamka' => '',
        ]);

        $body = (string) $this->get(route('kola.ical'))->assertOk()->content();

        $this->assertStringContainsString('BEGIN:VEVENT', $body);
        $this->assertStringContainsString('SUMMARY:01/2026', $body);
        // Závodní okno 08:00–11:00 UTC na den konání.
        $this->assertStringContainsString('DTSTART:20260117T080000Z', $body);
        $this->assertStringContainsString('DTEND:20260117T110000Z', $body);
        $this->assertStringContainsString('UID:kolo-', $body);
        // Upomínka 2 dny předem (VALARM s triggerem -P2D).
        $this->assertStringContainsString('BEGIN:VALARM', $body);
        $this->assertStringContainsString('TRIGGER:-P2D', $body);
        // CRLF řádkování dle RFC 5545.
        $this->assertStringContainsString("\r\n", $body);
    }

    public function test_evaluated_rounds_show_date_and_unevaluated_show_dash(): void
    {
        VkvpaKola::create([
            'datum_konani' => '2026-01-17',
            'datum_uzaverky' => '2026-02-01',
            'nazev' => '01/2026',
            'poznamka' => '',
            'vyhodnoceno' => '2026-02-05 10:00:00',
        ]);
        VkvpaKola::create([
            'datum_konani' => '2026-04-19',
            'datum_uzaverky' => '2026-05-03',
            'nazev' => '02/2026',
            'poznamka' => '',
            'vyhodnoceno' => null,
        ]);

        $this->get(route('kola.index'))
            ->assertOk()
            ->assertSee('5. 2. 2026')
            ->assertSee('—');
    }
}
