<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Services\Scoring\SkokanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * „Skokan" – body-delta oproti poslednímu startu závodníka ve stejné kategorii.
 */
class SkokanTest extends TestCase
{
    use RefreshDatabase;

    private EdiCategory $kat;

    private EdiRound $r1;

    private EdiRound $r2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);
        $this->r1 = EdiRound::create(['starts_at' => '2026-05-17', 'closes_at' => '2026-05-22 23:59:59', 'name' => '05/2026', 'note' => '', 'evaluated_at' => '2026-05-23 10:00:00']);
        $this->r2 = EdiRound::create(['starts_at' => '2026-06-21', 'closes_at' => '2026-06-26 23:59:59', 'name' => '06/2026', 'note' => '']);
    }

    private function entry(EdiRound $kolo, string $znacka, int $body): EdiEntry
    {
        return EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $this->kat->id, 'callsign' => $znacka,
            'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => $body,
            'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 1,
        ]);
    }

    public function test_body_delta_and_top_climber_are_computed(): void
    {
        $this->entry($this->r1, 'OK1A', 100);
        $this->entry($this->r1, 'OK1B', 80);

        $a = $this->entry($this->r2, 'OK1A', 150);  // +50 → největší skokan
        $b = $this->entry($this->r2, 'OK1B', 60);   // −20
        $c = $this->entry($this->r2, 'OK1C', 200);  // bez předchozího startu

        $radky = EdiEntry::query()->where('round_id', $this->r2->id)->get();
        $map = app(SkokanService::class)->bodyDeltas($this->r2, $radky);

        $this->assertSame(50, $map[$a->id]['delta']);
        $this->assertTrue($map[$a->id]['top']);

        $this->assertSame(-20, $map[$b->id]['delta']);
        $this->assertFalse($map[$b->id]['top']);

        $this->assertNull($map[$c->id]['delta']);
        $this->assertFalse($map[$c->id]['top']);
    }

    public function test_previous_start_skips_rounds_without_participation(): void
    {
        // OK1A startoval v r1, v mezikole ne – porovnává se s posledním startem (r1).
        $mezikolo = EdiRound::create(['starts_at' => '2026-06-01', 'closes_at' => '2026-06-06 23:59:59', 'name' => 'X/2026', 'note' => '', 'evaluated_at' => '2026-06-07 10:00:00']);
        $this->entry($this->r1, 'OK1A', 100);
        $this->entry($mezikolo, 'OK1B', 999); // jiná značka, ať mezikolo není prázdné

        $a = $this->entry($this->r2, 'OK1A', 130);

        $radky = EdiEntry::query()->where('round_id', $this->r2->id)->get();
        $map = app(SkokanService::class)->bodyDeltas($this->r2, $radky);

        $this->assertSame(30, $map[$a->id]['delta']);
    }

    public function test_different_category_is_not_compared(): void
    {
        $kat2 = EdiCategory::create(['name' => '432 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);
        // Předchozí start v jiné kategorii se nezapočítá.
        EdiEntry::create([
            'round_id' => $this->r1->id, 'category_id' => $kat2->id, 'callsign' => 'OK1A',
            'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => 100,
            'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 1,
        ]);

        $a = $this->entry($this->r2, 'OK1A', 150); // v kat (A), předchozí start byl v kat2 → null

        $radky = EdiEntry::query()->where('round_id', $this->r2->id)->get();
        $map = app(SkokanService::class)->bodyDeltas($this->r2, $radky);

        $this->assertNull($map[$a->id]['delta']);
    }

    public function test_listina_renders_delta_and_skokan_badge(): void
    {
        $this->entry($this->r1, 'OK1A', 100);
        $this->entry($this->r2, 'OK1A', 150);

        $this->get(route('vysledkova_listina', ['kolo' => $this->r2->id]))
            ->assertOk()
            ->assertSee('+50')
            ->assertSee('badge-skokan'); // odznak „skokan" je teď jazykově nezávislá ikona (raketa)
    }
}
