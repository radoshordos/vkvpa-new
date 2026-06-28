<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\EdiRound;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Výsledková listina – rozdělení po kategoriích, výpočet b/QSO, vyhledávání.
 */
class VysledkyListinaTest extends TestCase
{
    use RefreshDatabase;

    private function round(): EdiRound
    {
        return EdiRound::create([
            'starts_at' => now()->subDays(10),
            'closes_at' => now()->subDay(),
            'name' => '05/2026',
            'note' => '',
        ]);
    }

    private function kat(string $nazev): EdiCategory
    {
        return EdiCategory::create(['name' => $nazev, 'band' => $nazev, 'section' => 'SO', 'variant' => 'domestic']);
    }

    private function entry(int $kolo, int $kat, string $znacka, int $pocet, int $nas, int $body, int $poradi): EdiEntry
    {
        return EdiEntry::create([
            'round_id' => $kolo, 'category_id' => $kat, 'callsign' => $znacka,
            'locator' => 'JN99AJ', 'qso_count' => $pocet, 'qso_points' => 0,
            'multiplier' => $nas, 'points' => $body, 'rank' => $poradi,
            'approved' => true, 'sent' => false,
        ]);
    }

    public function test_listina_splits_results_by_category_and_computes_b_per_qso(): void
    {
        $kolo = $this->round();
        $single = $this->kat('144 MHz single op');
        $uhf = $this->kat('432 MHz single op');
        $this->entry($kolo->id, $single->id, 'OK1DOL', 139, 41, 24272, 1);
        $this->entry($kolo->id, $uhf->id, 'OK5SE', 53, 21, 4536, 1);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('144 MHz single op')
            ->assertSee('432 MHz single op')
            ->assertSee('OK1DOL')
            ->assertSee('OK5SE')
            ->assertSee('4,3 b/QSO'); // 24272 / (41 * 139) ≈ 4,26 → 4,3
    }

    public function test_search_filters_by_callsign(): void
    {
        $kolo = $this->round();
        $kat = $this->kat('144 MHz single op');
        $this->entry($kolo->id, $kat->id, 'OK1DOL', 139, 41, 24272, 1);
        $this->entry($kolo->id, $kat->id, 'OK2VZE', 146, 26, 13234, 2);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id, 'hledat' => 'OK1DOL']))
            ->assertOk()
            ->assertSee('OK1DOL')
            ->assertDontSee('OK2VZE');
    }

    public function test_unapproved_rows_are_hidden_from_public(): void
    {
        $kolo = $this->round();
        $kat = $this->kat('144 MHz single op');
        $this->entry($kolo->id, $kat->id, 'OK1DOL', 10, 5, 100, 1)
            ->update(['approved' => false]);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertDontSee('OK1DOL');
    }

    public function test_lp_filter_includes_lp_and_qrp_stations(): void
    {
        $kolo = $this->round();
        $kat = $this->kat('144 MHz single op');

        // QRP (≤5 W) je podmnožinou LP (<100 W), proto „jen LP" zahrnuje obě.
        $this->entry($kolo->id, $kat->id, 'OK1LP', 10, 5, 300, 1)->update(['lp' => true]);
        $this->entry($kolo->id, $kat->id, 'OK1QRP', 10, 5, 200, 2)->update(['qrp' => true]);
        $this->entry($kolo->id, $kat->id, 'OK1FULL', 10, 5, 500, 3);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id, 'lp' => 1]))
            ->assertOk()
            ->assertSee('OK1LP')
            ->assertSee('OK1QRP')
            ->assertDontSee('OK1FULL');
    }

    public function test_admin_sees_unapproved_rows(): void
    {
        $kolo = $this->round();
        $kat = $this->kat('144 MHz single op');
        $this->entry($kolo->id, $kat->id, 'OK1NEW', 10, 5, 100, 0)
            ->update(['approved' => false]);

        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('OK1NEW');
    }

    /** Kolo s otevřeným upload oknem (závod proběhl, uzávěrka v budoucnu). */
    private function aktivniKolo(): EdiRound
    {
        return EdiRound::create([
            'starts_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'name' => '06/2026',
            'note' => '',
        ]);
    }

    private function entryWithEdi(int $kolo, int $kat, string $znacka): EdiEntry
    {
        $head = Edihead::create([
            'round_id' => $kolo, 't_date' => '20260315;20260315', 'p_call' => $znacka,
            'p_wwlo' => 'JN99AJ', 'p_sect' => '', 'p_band' => '', 'r_name' => 'X',
            'r_phon' => '', 'r_emai' => '', 's_powe' => 100, 'src' => 'x',
        ]);
        $row = $this->entry($kolo, $kat, $znacka, 10, 5, 100, 1);
        $row->update(['edi_head_id' => $head->id]);

        return $row;
    }

    /**
     * Regrese: během otevřeného okna jednoho kola se hostovi NEsmí skrýt
     * odkazy EDI u jiných (uzavřených) kol.
     */
    public function test_edi_links_visible_for_guest_on_closed_round_during_another_window(): void
    {
        $this->aktivniKolo();                       // jiné kolo má otevřené okno
        $uzavrene = $this->round();                  // toto kolo je už uzavřené
        $kat = $this->kat('144 MHz single op');
        $this->entryWithEdi($uzavrene->id, $kat->id, 'OK1DOL');

        $this->get(route('vysledkova_listina', ['kolo' => $uzavrene->id]))
            ->assertOk()
            ->assertSee('soubor-redukovany')         // odkazy EDI/EDIR se vykreslily
            ->assertDontSee(__('app.edi_restricted_label'));
    }

    /** Na zobrazeném kole s otevřeným oknem se hostovi odkazy EDI skryjí. */
    public function test_edi_links_hidden_for_guest_on_active_round(): void
    {
        $aktivni = $this->aktivniKolo();
        $kat = $this->kat('144 MHz single op');
        $this->entryWithEdi($aktivni->id, $kat->id, 'OK1DOL');

        $this->get(route('vysledkova_listina', ['kolo' => $aktivni->id]))
            ->assertOk()
            ->assertSee(__('app.edi_restricted_label'));
    }
}
