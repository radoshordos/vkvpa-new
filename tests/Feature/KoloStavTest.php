<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KoloStav;
use App\Models\VkvpaKola;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Odvození fáze kola ({@see VkvpaKola::stav()}) a popisky {@see KoloStav}.
 * Stav je čistá funkce času: datum_konani (start), konec závodu (+3 h),
 * datum_uzaverky a vyhodnoceno.
 */
class KoloStavTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Pevné „teď" pro deterministické porovnání s datum_konani / datum_uzaverky.
        Carbon::setTestNow('2026-06-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attrs */
    private function kolo(array $attrs): VkvpaKola
    {
        return new VkvpaKola(array_merge([
            'nazev' => 'Test',
            'poznamka' => '',
            'vyhodnoceno' => null,
        ], $attrs));
    }

    public function test_vyhodnocene_is_terminal(): void
    {
        // Vyplněné vyhodnoceno přebije i právě běžící závodní okno.
        $kolo = $this->kolo(['vyhodnoceno' => '2026-06-12 10:00:00', 'datum_konani' => '2026-06-15 08:00:00', 'datum_uzaverky' => '2026-06-26 23:59:59']);
        $this->assertSame(KoloStav::Vyhodnocene, $kolo->stav());
    }

    public function test_running_contest_window_is_aktivni(): void
    {
        // Teď je 12:00, závod startoval 10:00 → okno (3 h) běží do 13:00.
        $kolo = $this->kolo(['datum_konani' => '2026-06-15 10:00:00', 'datum_uzaverky' => '2026-06-19 23:59:59']);
        $this->assertSame(KoloStav::Aktivni, $kolo->stav());
    }

    public function test_future_round_is_nadchazejici(): void
    {
        $kolo = $this->kolo(['datum_konani' => '2026-07-19 08:00:00', 'datum_uzaverky' => '2026-07-24 23:59:59']);
        $this->assertSame(KoloStav::Nadchazejici, $kolo->stav());
    }

    public function test_after_contest_before_deadline_is_prijem(): void
    {
        // Závod (14. 6. 08:00–11:00) už proběhl, uzávěrka (19. 6.) ještě ne.
        $kolo = $this->kolo(['datum_konani' => '2026-06-14 08:00:00', 'datum_uzaverky' => '2026-06-19 23:59:59']);
        $this->assertSame(KoloStav::Prijem, $kolo->stav());
    }

    public function test_after_deadline_unevaluated_is_uzavrene(): void
    {
        $kolo = $this->kolo(['datum_konani' => '2026-06-07 08:00:00', 'datum_uzaverky' => '2026-06-12 23:59:59']);
        $this->assertSame(KoloStav::Uzavrene, $kolo->stav());
    }

    public function test_deadline_caps_contest_window(): void
    {
        // Degenerovaná konfigurace: uzávěrka uprostřed závodního okna.
        // Po uzávěrce se hlášení nepřijímají, i kdyby okno ještě „běželo".
        $kolo = $this->kolo(['datum_konani' => '2026-06-15 10:00:00', 'datum_uzaverky' => '2026-06-15 11:00:00']);
        $this->assertSame(KoloStav::Uzavrene, $kolo->stav());
        $this->assertFalse($kolo->prijimaHlaseni());
    }

    public function test_konec_zavodu_follows_start_shift(): void
    {
        // Posunutý start zachová délku závodního okna (0800–1100 → 3 hodiny).
        $kolo = $this->kolo(['datum_konani' => '2026-06-15 09:30:00', 'datum_uzaverky' => '2026-06-19 23:59:59']);
        $this->assertSame('2026-06-15 12:30:00', $kolo->konecZavodu()->toDateTimeString());
    }

    public function test_labels_cover_all_states(): void
    {
        $this->assertSame('Nadcházející', KoloStav::Nadchazejici->label());
        $this->assertSame('Probíhá', KoloStav::Aktivni->label());
        $this->assertSame('Příjem hlášení', KoloStav::Prijem->label());
        $this->assertSame('Zpracování výsledků', KoloStav::Uzavrene->label());
        $this->assertSame('Vyhodnocené', KoloStav::Vyhodnocene->label());
    }
}
