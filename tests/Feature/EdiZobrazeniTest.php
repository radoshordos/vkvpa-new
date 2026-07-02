<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiController;
use App\Models\EdiEntry;
use App\Models\EdiHead;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zobrazení EDI deníku: akce EDI (původní) a EDIR (redukovaný na 08–11 UTC).
 * Přístupová pravidla: admin vždy, přihlášený mimo window, v době window 403.
 *
 * @see EdiController
 */
class EdiZobrazeniTest extends TestCase
{
    use RefreshDatabase;

    private function denik(?int $idKola = null): EdiHead
    {
        $raw = implode("\n", [
            '[REG1TEST;1]',
            'PCall=OK2KJT',
            '[QSORecords;2]',
            '260315;0801;OK1A;1;59;001;59;001;;JN99BP;2;;;;', // 08:01 → v okně
            '260315;1200;OK1Z;1;59;002;59;002;;JN99BP;2;;;;', // 12:00 → mimo okno
            '[END;]',
        ])."\n";

        return EdiHead::create([
            'round_id' => $idKola,
            't_date' => '20260315;20260315', 'p_call' => 'OK2KJT', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => 'X', 'r_phon' => '', 'r_emai' => '',
            's_powe' => 100, 'src' => $raw,
        ]);
    }

    private function admin(): User
    {
        return $this->makeUser('admin', isAdmin: true);
    }

    private function regularUser(): User
    {
        return $this->makeUser('user');
    }

    /** Kolo s otevřeným upload oknem (závod proběhl, uzávěrka v budoucnu). */
    private function activeRound(): EdiRound
    {
        return EdiRound::create([
            'name' => 'Test kolo',
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'note' => '',
        ]);
    }

    // ─── Obsah EDI (admin má vždy přístup) ────────────────────────────────────

    public function test_shows_original_edi(): void
    {
        $head = $this->denik();

        $this->actingAs($this->admin())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A')
            ->assertSee('OK1Z')
            ->assertSee('[QSORecords;2]');
    }

    public function test_shows_reduced_edi(): void
    {
        $head = $this->denik();

        $this->actingAs($this->admin())
            ->get(route('edi.soubor.redukovany', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A')
            ->assertDontSee('OK1Z')
            ->assertSee('[QSORecords;1]');
    }

    public function test_missing_src_returns_404(): void
    {
        $head = $this->denik();
        $head->update(['src' => null]);

        $this->actingAs($this->admin())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertNotFound();
    }

    // ─── Přístupová pravidla ───────────────────────────────────────────────────

    public function test_guest_sees_edi_when_no_active_round(): void
    {
        $head = $this->denik();

        $this->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk();
    }

    public function test_guest_sees_edir_when_no_active_round(): void
    {
        $head = $this->denik();

        $this->get(route('edi.soubor.redukovany', ['head' => $head->id]))
            ->assertOk();
    }

    public function test_authenticated_user_can_view_edi_when_no_active_round(): void
    {
        $head = $this->denik();

        $this->actingAs($this->regularUser())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A');
    }

    public function test_edi_blocked_during_upload_window_for_guest(): void
    {
        $kolo = $this->activeRound();
        $head = $this->denik($kolo->id);

        $this->get(route('edi.soubor', ['head' => $head->id]))
            ->assertForbidden();
    }

    public function test_edi_blocked_during_upload_window_for_regular_user(): void
    {
        $kolo = $this->activeRound();
        $head = $this->denik($kolo->id);

        $this->actingAs($this->regularUser())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertForbidden();
    }

    public function test_edir_blocked_during_upload_window_for_regular_user(): void
    {
        $kolo = $this->activeRound();
        $head = $this->denik($kolo->id);

        $this->actingAs($this->regularUser())
            ->get(route('edi.soubor.redukovany', ['head' => $head->id]))
            ->assertForbidden();
    }

    /**
     * Regrese: okno jednoho kola nesmí schovat deníky JINÝCH (uzavřených) kol.
     * Veřejnost si i během příjmu hlášení smí prohlížet deníky starých kol.
     */
    public function test_edi_of_closed_round_stays_public_during_another_rounds_window(): void
    {
        $this->activeRound();                       // jiné kolo má otevřené okno
        $stare = $this->closedRound();              // toto kolo je už uzavřené
        $head = $this->denik($stare->id);

        $this->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A');
    }

    /** Deník bez vazby na kolo (round_id = null) se během okna neblokuje. */
    public function test_edi_without_round_is_not_blocked(): void
    {
        $this->activeRound();
        $head = $this->denik();                     // round_id = null

        $this->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk();
    }

    public function test_admin_can_view_edi_during_upload_window(): void
    {
        $kolo = $this->activeRound();
        $head = $this->denik($kolo->id);

        $this->actingAs($this->admin())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A');
    }

    public function test_admin_can_view_edir_during_upload_window(): void
    {
        $kolo = $this->activeRound();
        $head = $this->denik($kolo->id);

        $this->actingAs($this->admin())
            ->get(route('edi.soubor.redukovany', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A');
    }

    // ─── Po uzávěrce je EDI veřejné (okno je čistá funkce času) ───────────────

    public function test_unapproved_record_in_closed_round_does_not_block_edi_view(): void
    {
        $kolo = $this->closedRound();
        $this->unapprovedEntry($kolo->id);
        $head = $this->denik();

        $this->actingAs($this->regularUser())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A');
    }

    private function closedRound(): EdiRound
    {
        return EdiRound::create([
            'name' => 'Staré kolo',
            'starts_at' => now()->subDays(60),
            'closes_at' => now()->subDays(50),
            'note' => '',
        ]);
    }

    private function unapprovedEntry(int $idKola): EdiEntry
    {
        return EdiEntry::create([
            'round_id' => $idKola, 'callsign' => 'OK9ZAP', 'locator' => 'JN99AJ',
            'qso_count' => 0, 'qso_points' => 0, 'multiplier' => 0, 'points' => 0,
            'approved' => false, 'sent' => false,
        ]);
    }
}
