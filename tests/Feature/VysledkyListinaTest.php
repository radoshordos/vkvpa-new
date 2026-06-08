<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Výsledková listina – rozdělení po kategoriích, výpočet b/QSO, vyhledávání.
 */
class VysledkyListinaTest extends TestCase
{
    use RefreshDatabase;

    private function kolo(): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => now()->subDays(10),
            'datum_uzaverky' => now()->subDay(),
            'nazev' => '05/2026',
            'poznamka' => '',
        ]);
    }

    private function kat(string $nazev): VkvpaKategorie
    {
        return VkvpaKategorie::create(['nazev' => $nazev, 'popis' => '', 'zkratka' => $nazev, 'dxid' => 0]);
    }

    private function entry(int $kolo, int $kat, string $znacka, int $pocet, int $nas, int $body, int $poradi): VkvpaData
    {
        return VkvpaData::create([
            'id_kola' => $kolo, 'id_kategorie' => $kat, 'znacka' => $znacka,
            'locator' => 'JN99AJ', 'pocet' => $pocet, 'bodu_za_qso' => 0,
            'nasobice' => $nas, 'body' => $body, 'poradi' => $poradi,
            'schvaleno' => true, 'odeslano' => false,
        ]);
    }

    public function test_listina_splits_results_by_category_and_computes_b_per_qso(): void
    {
        $kolo = $this->kolo();
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
        $kolo = $this->kolo();
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
        $kolo = $this->kolo();
        $kat = $this->kat('144 MHz single op');
        $this->entry($kolo->id, $kat->id, 'OK1DOL', 10, 5, 100, 1)
            ->update(['schvaleno' => false]);

        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertDontSee('OK1DOL');
    }

    public function test_lp_filter_includes_lp_and_qrp_stations(): void
    {
        $kolo = $this->kolo();
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
        $kolo = $this->kolo();
        $kat = $this->kat('144 MHz single op');
        $this->entry($kolo->id, $kat->id, 'OK1NEW', 10, 5, 100, 0)
            ->update(['schvaleno' => false]);

        $admin = User::create(['name' => 'Admin', 'password' => Hash::make('x'), 'is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('OK1NEW');
    }
}
