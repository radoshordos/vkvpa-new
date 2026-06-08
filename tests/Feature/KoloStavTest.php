<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KoloStav;
use App\Models\VkvpaKola;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Odvození fáze kola ({@see VkvpaKola::stav()}) a popisky {@see KoloStav}.
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
            'aktivni' => false,
            'vyhodnoceno' => null,
        ], $attrs));
    }

    public function test_vyhodnocene_is_terminal(): void
    {
        // Vyplněné vyhodnoceno přebije i příznak aktivni.
        $kolo = $this->kolo(['aktivni' => true, 'vyhodnoceno' => '2026-06-12 10:00:00', 'datum_konani' => '2026-06-21']);
        $this->assertSame(KoloStav::Vyhodnocene, $kolo->stav());
    }

    public function test_aktivni_round_is_probiha(): void
    {
        $kolo = $this->kolo(['aktivni' => true, 'datum_konani' => '2026-06-21', 'datum_uzaverky' => '2026-06-26 23:59:59']);
        $this->assertSame(KoloStav::Aktivni, $kolo->stav());
    }

    public function test_future_round_is_nadchazejici(): void
    {
        $kolo = $this->kolo(['datum_konani' => '2026-07-19', 'datum_uzaverky' => '2026-07-24 23:59:59']);
        $this->assertSame(KoloStav::Nadchazejici, $kolo->stav());
    }

    public function test_after_contest_before_deadline_is_prijem(): void
    {
        // Den závodu (14. 6.) už proběhl, uzávěrka (19. 6.) ještě ne, kolo není aktivní.
        $kolo = $this->kolo(['datum_konani' => '2026-06-14', 'datum_uzaverky' => '2026-06-19 23:59:59']);
        $this->assertSame(KoloStav::Prijem, $kolo->stav());
    }

    public function test_after_deadline_unevaluated_is_uzavrene(): void
    {
        $kolo = $this->kolo(['datum_konani' => '2026-06-07', 'datum_uzaverky' => '2026-06-12 23:59:59']);
        $this->assertSame(KoloStav::Uzavrene, $kolo->stav());
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
