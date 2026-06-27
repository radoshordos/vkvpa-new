<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\StatistikyController;
use App\Models\EdiCategory;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaData;
use App\Models\VkvpaKola;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Veřejná stránka Statistiky kol (rozcestník + detail kola).
 *
 * @see StatistikyController
 */
class StatistikyTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_evaluated_rounds(): void
    {
        VkvpaKola::create([
            'datum_konani' => '2026-03-15', 'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026', 'poznamka' => '', 'vyhodnoceno' => '2026-03-21 10:00:00',
        ]);
        // Nadcházející kolo (nevyhodnocené) se v rozcestníku zobrazit nesmí.
        VkvpaKola::create([
            'datum_konani' => now()->addMonth()->toDateTimeString(),
            'datum_uzaverky' => now()->addMonths(2)->toDateTimeString(),
            'nazev' => '07/2026', 'poznamka' => '',
        ]);

        $this->get(route('statistiky.index'))
            ->assertOk()
            ->assertSee('03/2026')
            ->assertDontSee('07/2026');
    }

    public function test_detail_returns_404_for_non_evaluated_round(): void
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->addMonth()->toDateTimeString(),
            'datum_uzaverky' => now()->addMonths(2)->toDateTimeString(),
            'nazev' => '07/2026', 'poznamka' => '',
        ]);

        $this->get(route('statistiky.kolo', ['kolo' => $kolo->id]))
            ->assertNotFound();
    }

    public function test_detail_renders_aggregates_for_evaluated_round(): void
    {
        $kolo = $this->seedEvaluatedRound();

        $html = $this->get(route('statistiky.kolo', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('window.__statConfig', false)
            ->assertSee('OK5BIG')   // stanice kola + ODX kola
            ->assertSee('JN99')     // obsazený velký čtverec (heatmapa)
            ->assertSee('OK1AAA')   // TOP žebříček (značka s nejvíc body)
            ->assertSee('ODX')
            // Grafy (Chart.js plátna) + vrstva účastníků.
            ->assertSee('chartTimeline')
            ->assertSee('chartMody')
            ->assertSee('chartKategorie')
            ->assertSee('chartTrend')
            ->assertSee('data-stat-layer="ucastnici"', false)
            ->assertSee('data-stat-layer="tok"', false)
            ->assertSee(route('statistiky.kolo.og', ['kolo' => $kolo->id], false), false) // OG náhled
            // Toto kolo je jediné vyhodnocené → drží všechny all-time rekordy
            // → odznak rekordní účasti.
            ->assertSee(__('pages.stat.badge_ucast'))
            ->getContent() ?: '';

        // Heatmapa, stanice i účastníci se předávají JS configu.
        $compact = str_replace(' ', '', $html);
        $this->assertStringContainsString('"square":"JN99"', $compact);
        $this->assertStringContainsString('"call":"OK5BIG"', $compact);
        $this->assertStringContainsString('"counts":', $compact); // časová osa
        $this->assertStringContainsString('ucastnici:', $compact); // vrstva účastníků
    }

    public function test_index_shows_hall_of_fame(): void
    {
        $this->seedEvaluatedRound();

        $this->get(route('statistiky.index'))
            ->assertOk()
            ->assertSee(__('pages.stat.hall_heading'))
            ->assertSee(__('pages.stat.rec_skore'))
            ->assertSee('OK1AAA'); // držitel nejvyššího skóre (80 b.)
    }

    public function test_og_image_renders_png_for_evaluated_round(): void
    {
        $kolo = $this->seedEvaluatedRound();

        $res = $this->get(route('statistiky.kolo.og', ['kolo' => $kolo->id]));
        $res->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", (string) $res->getContent());
    }

    public function test_og_image_returns_404_for_non_evaluated_round(): void
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => now()->addMonth()->toDateTimeString(),
            'datum_uzaverky' => now()->addMonths(2)->toDateTimeString(),
            'nazev' => '07/2026', 'poznamka' => '',
        ]);

        $this->get(route('statistiky.kolo.og', ['kolo' => $kolo->id]))->assertNotFound();
    }

    public function test_precompute_odx_command_populates_hall_of_fame(): void
    {
        $this->seedEvaluatedRound();

        $this->assertSame(0, Artisan::call('statistiky:precompute-odx'));

        // All-time ODX (nejdelší spojení) se po předpočítání objeví v síni slávy.
        $this->get(route('statistiky.index'))
            ->assertOk()
            ->assertSee(__('pages.stat.rec_odx'))
            ->assertSee('OK5BIG'); // pracovaná stanice nejdelšího spojení
    }

    public function test_station_profile_renders_history(): void
    {
        $kolo = $this->seedEvaluatedRound();

        $this->get(route('statistiky.stanice', ['znacka' => 'OK1AAA']))
            ->assertOk()
            ->assertSee('OK1AAA')
            ->assertSee(__('pages.stat.s_history'))
            ->assertSee('03/2026') // kolo v historii
            ->assertSee(route('statistiky.kolo', ['kolo' => $kolo->id], false));
    }

    public function test_station_profile_returns_404_for_unknown_call(): void
    {
        $this->seedEvaluatedRound();

        $this->get(route('statistiky.stanice', ['znacka' => 'NONEXIST']))
            ->assertNotFound();
    }

    public function test_results_listing_links_to_station_profile(): void
    {
        $kolo = $this->seedEvaluatedRound();

        // Veřejnost (ne-admin) vidí značku jako odkaz na profil stanice.
        $this->get(route('vysledkova_listina', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee(route('statistiky.stanice', ['znacka' => 'OK1AAA'], false));
    }

    /**
     * Vyhodnocené kolo se dvěma deníky (OK5BIG pracován napříč oběma) a dvěma
     * převzatými záznamy listiny pro žebříčky/souhrn.
     */
    private function seedEvaluatedRound(): VkvpaKola
    {
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15', 'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026', 'poznamka' => '', 'vyhodnoceno' => '2026-03-21 10:00:00',
        ]);

        $headA = Edihead::create(['id_kola' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1AAA', 'p_wwlo' => 'JN79', 'p_band' => '144 MHz', 'r_name' => 'A', 'r_emai' => 'a@a.cz', 's_powe' => 100]);
        $headB = Edihead::create(['id_kola' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89', 'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100]);

        foreach (['0810', '0811', '0812'] as $t) {
            Ediline::create(['edihead_id' => $headA->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        }
        foreach (['0820', '0821'] as $t) {
            Ediline::create(['edihead_id' => $headB->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        }

        $kat = EdiCategory::create(['name' => '144 MHz', 'band' => 'A', 'section' => 'SO', 'variant' => 'domestic']);

        foreach ([['OK1AAA', 5, 4, 80], ['OK1BBB', 3, 3, 30]] as [$znacka, $pocet, $nasobice, $body]) {
            VkvpaData::create([
                'id_kola' => $kolo->id, 'id_kategorie' => $kat->id,
                'qrp' => false, 'lp' => false, 'znacka' => $znacka, 'locator' => 'JN79AA',
                'pocet' => $pocet, 'bodu_za_qso' => 20, 'nasobice' => $nasobice, 'body' => $body,
                'jmeno' => 'Test', 'mail' => 't@t.cz', 'telefon' => '', 'poznamka' => '',
                'soapbox' => '', 'ip' => '', 'edihead_id' => null,
                'poradi' => 1, 'schvaleno' => true, 'session_id' => '',
            ]);
        }

        return $kolo;
    }
}
