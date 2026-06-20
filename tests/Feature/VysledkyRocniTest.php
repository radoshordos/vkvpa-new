<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\VysledkyController;
use App\Models\VkvpaData;
use App\Models\VkvpaKategorie;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roční výsledky – agregace bodů přes všechna kola daného roku.
 *
 * @see VysledkyController::rocni()
 */
class VysledkyRocniTest extends TestCase
{
    use RefreshDatabase;

    private function kat(string $nazev): VkvpaKategorie
    {
        return VkvpaKategorie::create(['nazev' => $nazev, 'zkratka' => $nazev, 'dxid' => 0]);
    }

    private function kolo(string $rok): VkvpaKola
    {
        return VkvpaKola::create([
            'datum_konani' => "{$rok}-01-18",
            'datum_uzaverky' => "{$rok}-02-01",
            'nazev' => "01/{$rok}",
            'poznamka' => '',
        ]);
    }

    private function entry(VkvpaKola $kolo, VkvpaKategorie $kat, string $znacka, int $body, bool $qrp = false, bool $edi = true, bool $lp = false): VkvpaData
    {
        return VkvpaData::create([
            'id_kola' => $kolo->id,
            'id_kategorie' => $kat->id,
            'znacka' => $znacka,
            'locator' => 'JN99AJ',
            'pocet' => 10,
            'nasobice' => 5,
            'bodu_za_qso' => 0,
            'body' => $body,
            'poradi' => 1,
            'schvaleno' => true,
            'odeslano' => false,
            'qrp' => $qrp,
            'lp' => $lp,
            // FK na edihead se v sqlite testech nevynucuje – id 1 nemusí existovat.
            'edihead_id' => $edi ? 1 : null,
        ]);
    }

    public function test_rocni_page_loads(): void
    {
        $this->get(route('rocni_vysledky'))->assertOk()->assertSee('Roční výsledky');
    }

    public function test_rocni_shows_empty_state_for_year_without_results(): void
    {
        $this->get(route('rocni_vysledky', ['rok' => 2000]))
            ->assertOk()
            ->assertSee('nejsou žádné vyhodnocené výsledky');
    }

    public function test_rocni_sums_points_across_rounds_for_same_year(): void
    {
        $kat = $this->kat('144 MHz single op');
        $kolo1 = $this->kolo('2026');
        $kolo2 = VkvpaKola::create([
            'datum_konani' => '2026-04-19',
            'datum_uzaverky' => '2026-05-03',
            'nazev' => '02/2026',
            'poznamka' => '',
        ]);

        $this->entry($kolo1, $kat, 'OK1DOL', 1000);
        $this->entry($kolo2, $kat, 'OK1DOL', 500);

        $this->get(route('rocni_vysledky', ['rok' => 2026]))
            ->assertOk()
            ->assertSee('OK1DOL')
            ->assertSee('1500');
    }

    public function test_rocni_shows_per_round_monthly_breakdown(): void
    {
        $kat = $this->kat('144 MHz single op');
        $kolo1 = $this->kolo('2026'); // 01/2026
        $kolo2 = VkvpaKola::create([
            'datum_konani' => '2026-04-19',
            'datum_uzaverky' => '2026-05-03',
            'nazev' => '04/2026',
            'poznamka' => '',
        ]);

        $this->entry($kolo1, $kat, 'OK1DOL', 1000);
        $this->entry($kolo2, $kat, 'OK1DOL', 500);
        // Stanice jen v jednom kole – druhý sloupec musí mít prázdnou (—) hodnotu,
        // nikoli spadnout na chybějícím atributu (strict mód modelu).
        $this->entry($kolo1, $kat, 'OK2ONE', 700);

        // Sloupce za jednotlivá kola (měsíce 01 a 04) i celkový součet.
        $this->get(route('rocni_vysledky', ['rok' => 2026]))
            ->assertOk()
            ->assertSee('OK2ONE')
            ->assertSeeInOrder(['01', '04'])
            ->assertSeeInOrder(['1000', '500', '1500']);
    }

    public function test_rocni_excludes_results_from_other_year(): void
    {
        $kat = $this->kat('144 MHz single op');
        $kolo2025 = $this->kolo('2025');
        $kolo2026 = $this->kolo('2026');

        $this->entry($kolo2025, $kat, 'OK2OLD', 999);
        $this->entry($kolo2026, $kat, 'OK1NEW', 100);

        $this->get(route('rocni_vysledky', ['rok' => 2026]))
            ->assertOk()
            ->assertSee('OK1NEW')
            ->assertDontSee('OK2OLD');
    }

    public function test_rocni_qrp_filter_shows_only_qrp_stations(): void
    {
        $kat = $this->kat('144 MHz single op');
        $kolo = $this->kolo('2026');

        $this->entry($kolo, $kat, 'OK1QRP', 200, qrp: true);
        $this->entry($kolo, $kat, 'OK1FULL', 500, qrp: false);

        $this->get(route('rocni_vysledky', ['rok' => 2026, 'qrp' => 1]))
            ->assertOk()
            ->assertSee('OK1QRP')
            ->assertDontSee('OK1FULL');
    }

    public function test_rocni_lp_filter_includes_lp_and_qrp_stations(): void
    {
        $kat = $this->kat('144 MHz single op');
        $kolo = $this->kolo('2026');

        // QRP (≤5 W) je podmnožinou LP (<100 W) – filtr „jen LP" musí zahrnout obě.
        $this->entry($kolo, $kat, 'OK1LP', 300, lp: true);
        $this->entry($kolo, $kat, 'OK1QRP', 200, qrp: true);
        $this->entry($kolo, $kat, 'OK1FULL', 500, qrp: false, lp: false);

        $this->get(route('rocni_vysledky', ['rok' => 2026, 'lp' => 1]))
            ->assertOk()
            ->assertSee('OK1LP')
            ->assertSee('OK1QRP')
            ->assertDontSee('OK1FULL');
    }

    public function test_rocni_tints_monthly_cell_by_power(): void
    {
        $kat = $this->kat('144 MHz single op');
        $kolo1 = $this->kolo('2026'); // 01/2026 – QRP
        $kolo4 = VkvpaKola::create([
            'datum_konani' => '2026-04-19',
            'datum_uzaverky' => '2026-05-03',
            'nazev' => '04/2026',
            'poznamka' => '',
        ]);

        // Stanice jede 01 QRP a 04 na plný výkon – obarvit se má jen lednová buňka.
        $this->entry($kolo1, $kat, 'OK1MIX', 200, qrp: true);
        $this->entry($kolo4, $kat, 'OK1MIX', 500);

        // QRP měsíc dostane podbarvení i title; plný výkon zůstává bez příznaku.
        // (cell-qrp/cell-lp jsou i v legendě, proto kontrolujeme title buňky.)
        $this->get(route('rocni_vysledky', ['rok' => 2026]))
            ->assertOk()
            ->assertSee('cell-qrp', escape: false)
            ->assertSee('title="QRP"', escape: false)
            ->assertDontSee('title="LP"', escape: false);
    }

    public function test_rocni_groups_by_category(): void
    {
        $kat144 = $this->kat('144 MHz single op');
        $kat432 = $this->kat('432 MHz single op');
        $kolo = $this->kolo('2026');

        $this->entry($kolo, $kat144, 'OK1VHF', 1000);
        $this->entry($kolo, $kat432, 'OK1UHF', 800);

        $this->get(route('rocni_vysledky', ['rok' => 2026]))
            ->assertOk()
            ->assertSee('144 MHz single op')
            ->assertSee('432 MHz single op')
            ->assertSee('OK1VHF')
            ->assertSee('OK1UHF');
    }

    public function test_rocni_category_filter_narrows_to_one_category(): void
    {
        $kat144 = $this->kat('144 MHz single op');
        $kat432 = $this->kat('432 MHz single op');
        $kolo = $this->kolo('2026');

        $this->entry($kolo, $kat144, 'OK1VHF', 1000);
        $this->entry($kolo, $kat432, 'OK1UHF', 800);

        $this->get(route('rocni_vysledky', ['rok' => 2026, 'kategorie' => $kat432->id]))
            ->assertOk()
            ->assertSee('OK1UHF')
            ->assertDontSee('OK1VHF');
    }
}
