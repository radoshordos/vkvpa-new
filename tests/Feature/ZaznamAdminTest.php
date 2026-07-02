<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\ZaznamController;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin akce nad záznamem výsledkové listiny: P (převzít/vrátit – toggle) a X (smazat).
 *
 * @see ZaznamController
 */
class ZaznamAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->makeUser('Admin', isAdmin: true);
    }

    /** Záznam v kole s otevřeným příjmem (stav Příjem) – převzetí lze i vracet. */
    private function zaznam(bool $approved = false): EdiEntry
    {
        $kolo = EdiRound::create([
            'starts_at' => now()->subDays(2),
            'closes_at' => now()->addDays(3),
            'name' => '05/2026',
            'note' => '',
        ]);
        $kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);

        return EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1TEST',
            'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => 50,
            'qso_points' => 0, 'approved' => $approved, 'sent' => false,
        ]);
    }

    public function test_admin_can_take_over_record(): void
    {
        $zaznam = $this->zaznam(false);

        $this->actingAs($this->admin())
            ->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $zaznam->round_id]))
            ->assertSessionHas('announcement');

        // Po převzetí je záznam „převzatý" (approved=true) → zmizí meruňkové pozadí.
        $this->assertTrue($zaznam->refresh()->approved);
    }

    public function test_admin_can_delete_record(): void
    {
        $zaznam = $this->zaznam(true);

        $this->actingAs($this->admin())
            ->delete(route('zaznam.destroy', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $zaznam->round_id]))
            ->assertSessionHas('announcement');

        $this->assertDatabaseMissing('edi_entries', ['id' => $zaznam->id]);
    }

    public function test_guest_cannot_use_admin_actions(): void
    {
        $zaznam = $this->zaznam(false);

        $this->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('login'));

        $this->assertFalse($zaznam->refresh()->approved);
    }

    public function test_admin_can_unapprove_record(): void
    {
        $zaznam = $this->zaznam(true);

        $this->actingAs($this->admin())
            ->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $zaznam->round_id]))
            ->assertSessionHas('announcement');

        // Toggle: převzatý záznam se vrátí mezi nepřevzaté (meruňkové).
        $this->assertFalse($zaznam->refresh()->approved);
    }

    public function test_prevzit_toggles_back_and_forth(): void
    {
        $zaznam = $this->zaznam(false);
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))->assertRedirect();
        $this->assertTrue($zaznam->refresh()->approved);

        $this->actingAs($admin)->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))->assertRedirect();
        $this->assertFalse($zaznam->refresh()->approved);
    }

    public function test_unapprove_resets_ranking_for_round(): void
    {
        $kolo = EdiRound::create(['starts_at' => now()->subDays(2), 'closes_at' => now()->addDays(3), 'name' => '05/2026', 'note' => '']);
        $kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);

        $a = EdiEntry::create(['round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1A', 'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => 100, 'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 1]);
        $b = EdiEntry::create(['round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1B', 'locator' => 'JN99AJ', 'qso_count' => 5, 'multiplier' => 3, 'points' => 50, 'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 2]);

        // Odebrání převzetí 1. místa: OK1B postoupí na 1., OK1A vypadne ze žebříčku (poradi 0).
        $this->actingAs($this->admin())
            ->patch(route('zaznam.update', ['zaznam' => $a->id]))
            ->assertRedirect();

        $this->assertFalse($a->refresh()->approved);
        $this->assertSame(0, $a->refresh()->rank);
        $this->assertSame(1, $b->refresh()->rank);
    }

    public function test_prevzit_recalculates_ranking_for_round(): void
    {
        $kolo = EdiRound::create(['starts_at' => now()->subDays(2), 'closes_at' => now()->addDays(3), 'name' => '05/2026', 'note' => '']);
        $kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);

        $a = EdiEntry::create(['round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1A', 'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => 100, 'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 0]);
        $b = EdiEntry::create(['round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1B', 'locator' => 'JN99AJ', 'qso_count' => 5, 'multiplier' => 3, 'points' => 50, 'qso_points' => 0, 'approved' => false, 'sent' => false, 'rank' => 0]);

        $this->actingAs($this->admin())
            ->patch(route('zaznam.update', ['zaznam' => $b->id]))
            ->assertRedirect();

        // Po převzetí musí ScoringService přepočítat pořadí.
        $this->assertSame(1, $a->refresh()->rank);
        $this->assertSame(2, $b->refresh()->rank);
    }

    public function test_smazat_recalculates_ranking_after_deletion(): void
    {
        $kolo = EdiRound::create(['starts_at' => now()->subDays(2), 'closes_at' => now()->addDays(3), 'name' => '05/2026', 'note' => '']);
        $kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);

        $a = EdiEntry::create(['round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1A', 'locator' => 'JN99AJ', 'qso_count' => 10, 'multiplier' => 5, 'points' => 100, 'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 2]);
        $b = EdiEntry::create(['round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1B', 'locator' => 'JN99AJ', 'qso_count' => 5, 'multiplier' => 3, 'points' => 200, 'qso_points' => 0, 'approved' => true, 'sent' => false, 'rank' => 1]);

        $this->actingAs($this->admin())
            ->delete(route('zaznam.destroy', ['zaznam' => $b->id]))
            ->assertRedirect();

        // Po smazání 1. místa musí OK1A přeskočit na 1. místo.
        $this->assertSame(1, $a->refresh()->rank);
        $this->assertDatabaseMissing('edi_entries', ['id' => $b->id]);
    }

    public function test_cannot_unapprove_after_deadline(): void
    {
        // Kolo po uzávěrce (stav Zpracování) – převzetí už nelze vrátit.
        $kolo = EdiRound::create(['starts_at' => now()->subDays(7), 'closes_at' => now()->subDay(), 'name' => '05/2026', 'note' => '']);
        $kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);
        $zaznam = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1A', 'locator' => 'JN99AJ',
            'qso_count' => 10, 'multiplier' => 5, 'points' => 50, 'qso_points' => 0, 'approved' => true, 'sent' => false,
        ]);

        $this->actingAs($this->admin())
            ->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))
            ->assertRedirect(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertSessionHas('announcement');

        // Záznam zůstává převzatý – odebrání po uzávěrce je zakázáno.
        $this->assertTrue($zaznam->refresh()->approved);
    }

    public function test_taking_over_last_record_after_deadline_evaluates_round(): void
    {
        // Kolo po uzávěrce s jediným dosud nepřevzatým záznamem.
        $kolo = EdiRound::create(['starts_at' => now()->subDays(7), 'closes_at' => now()->subDay(), 'name' => '05/2026', 'note' => '']);
        $kat = EdiCategory::create(['name' => '144 MHz single op', 'section' => 'SO', 'variant' => 'domestic']);
        $zaznam = EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id, 'callsign' => 'OK1A', 'locator' => 'JN99AJ',
            'qso_count' => 10, 'multiplier' => 5, 'points' => 50, 'qso_points' => 0, 'approved' => false, 'sent' => false,
        ]);
        $this->assertNull($kolo->evaluated_at);

        $this->actingAs($this->admin())
            ->patch(route('zaznam.update', ['zaznam' => $zaznam->id]))
            ->assertRedirect()
            ->assertSessionHas('announcement');

        // Převzetí posledního záznamu po uzávěrce kolo rovnou vyhodnotí.
        $this->assertTrue($zaznam->refresh()->approved);
        $this->assertNotNull($kolo->refresh()->evaluated_at);
    }
}
