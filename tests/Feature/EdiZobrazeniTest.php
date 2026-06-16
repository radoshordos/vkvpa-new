<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\EdiController;
use App\Models\Edihead;
use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    private function denik(): Edihead
    {
        $raw = implode("\n", [
            '[REG1TEST;1]',
            'PCall=OK2KJT',
            '[QSORecords;2]',
            '260315;0801;OK1A;1;59;001;59;001;;JN99BP;2;;;;', // 08:01 → v okně
            '260315;1200;OK1Z;1;59;002;59;002;;JN99BP;2;;;;', // 12:00 → mimo okno
            '[END;]',
        ])."\n";

        return Edihead::create([
            't_date' => '20260315;20260315', 'p_call' => 'OK2KJT', 'p_wwlo' => 'JN99AJ',
            'p_sect' => '', 'p_band' => '', 'r_name' => 'X', 'r_phon' => '', 'r_emai' => '',
            's_powe' => 100, 'src' => $raw,
        ]);
    }

    private function admin(): User
    {
        return User::create(['name' => 'admin', 'password' => Hash::make('x'), 'is_admin' => true]);
    }

    private function regularUser(): User
    {
        return User::create(['name' => 'user', 'password' => Hash::make('x'), 'is_admin' => false]);
    }

    /** Kolo s otevřeným upload oknem (závod proběhl, uzávěrka v budoucnu). */
    private function activeRound(): VkvpaKola
    {
        return VkvpaKola::create([
            'nazev' => 'Test kolo',
            'datum_konani' => now()->subDay(),
            'datum_uzaverky' => now()->addDay(),
            'poznamka' => '',
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
        $this->activeRound();
        $head = $this->denik();

        $this->get(route('edi.soubor', ['head' => $head->id]))
            ->assertForbidden();
    }

    public function test_edi_blocked_during_upload_window_for_regular_user(): void
    {
        $this->activeRound();
        $head = $this->denik();

        $this->actingAs($this->regularUser())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertForbidden();
    }

    public function test_edir_blocked_during_upload_window_for_regular_user(): void
    {
        $this->activeRound();
        $head = $this->denik();

        $this->actingAs($this->regularUser())
            ->get(route('edi.soubor.redukovany', ['head' => $head->id]))
            ->assertForbidden();
    }

    public function test_admin_can_view_edi_during_upload_window(): void
    {
        $this->activeRound();
        $head = $this->denik();

        $this->actingAs($this->admin())
            ->get(route('edi.soubor', ['head' => $head->id]))
            ->assertOk()
            ->assertSee('OK1A');
    }

    public function test_admin_can_view_edir_during_upload_window(): void
    {
        $this->activeRound();
        $head = $this->denik();

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

    private function closedRound(): VkvpaKola
    {
        return VkvpaKola::create([
            'nazev' => 'Staré kolo',
            'datum_konani' => now()->subDays(60),
            'datum_uzaverky' => now()->subDays(50),
            'poznamka' => '',
        ]);
    }

    private function unapprovedEntry(int $idKola): VkvpaData
    {
        return VkvpaData::create([
            'id_kola' => $idKola, 'znacka' => 'OK9ZAP', 'locator' => 'JN99AJ',
            'pocet' => 0, 'bodu_za_qso' => 0, 'nasobice' => 0, 'body' => 0,
            'schvaleno' => false, 'odeslano' => false,
        ]);
    }
}
