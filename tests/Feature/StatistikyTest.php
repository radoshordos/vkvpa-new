<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\StatistikyController;
use App\Models\EdiCategory;
use App\Models\EdiEntry;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\EdiRound;
use App\Services\Edi\KoloStatistiky;
use App\Services\Scoring\RekordyService;
use App\Services\Scoring\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Veřejná stránka Statistiky kol (rozcestník + detail kola).
 *
 * @see StatistikyController
 */
class StatistikyTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_evaluated_rounds_with_participants(): void
    {
        $visibleRound = EdiRound::create([
            'starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00',
        ]);
        $this->createApprovedEntry($visibleRound);

        $previousYearRound = EdiRound::create([
            'starts_at' => '2025-12-21', 'closes_at' => '2025-12-26 23:59:59',
            'name' => '12/2025', 'note' => '', 'evaluated_at' => '2025-12-27 10:00:00',
        ]);
        $this->createApprovedEntry($previousYearRound, 'OK1BBB');

        EdiRound::create([
            'starts_at' => '2026-04-19', 'closes_at' => '2026-04-24 23:59:59',
            'name' => '04/2026', 'note' => '', 'evaluated_at' => '2026-04-25 10:00:00',
        ]);
        // Nadcházející kolo (nevyhodnocené) se v rozcestníku zobrazit nesmí.
        EdiRound::create([
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'closes_at' => now()->addMonths(2)->toDateTimeString(),
            'name' => '07/2026', 'note' => '',
        ]);

        $this->get(route('statistiky.index'))
            ->assertOk()
            ->assertSee(__('pages.stat.archive_heading'))
            ->assertSee('03/2026')
            ->assertSee('href="#rok-2026"', false)
            ->assertSee('id="rok-2026"', false)
            ->assertSee('1 záznam')
            ->assertSee(__('pages.stat.card_entries_label'))
            ->assertSee(__('pages.stat.best_attendance_year'))
            ->assertDontSee('04/2026')
            ->assertDontSee('07/2026');
    }

    public function test_index_uses_round_statistics_url(): void
    {
        $this->assertSame('/statistiky-kol', route('statistiky.index', [], false));
    }

    public function test_detail_returns_404_for_non_evaluated_round(): void
    {
        $kolo = EdiRound::create([
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'closes_at' => now()->addMonths(2)->toDateTimeString(),
            'name' => '07/2026', 'note' => '',
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

    public function test_hall_of_fame_qso_record_uses_qso_count(): void
    {
        Cache::forget('vkvpa:rekordy:v2');
        $this->seedEvaluatedRound();

        $rekordy = app(RekordyService::class)->vrcholy();

        $this->assertNotNull($rekordy['qso']);
        $this->assertSame(5, $rekordy['qso']['value']);
    }

    public function test_rank_round_invalidates_round_statistics_and_record_cache(): void
    {
        $kolo = $this->seedEvaluatedRound();
        $statistiky = app(KoloStatistiky::class);
        $rekordy = app(RekordyService::class);

        $initial = $rekordy->vrcholy();

        $this->assertSame(110, $statistiky->prehled($kolo)['bodyCelkem']);
        $this->assertNotNull($initial['skore']);
        $this->assertSame(80, $initial['skore']['value']);

        EdiEntry::query()
            ->where('round_id', $kolo->id)
            ->where('callsign', 'OK1AAA')
            ->update(['points' => 120]);

        app(ScoringService::class)->rankRound($kolo->id);

        $updated = $rekordy->vrcholy();

        $this->assertSame(150, $statistiky->prehled($kolo)['bodyCelkem']);
        $this->assertNotNull($updated['skore']);
        $this->assertSame(120, $updated['skore']['value']);
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
        $kolo = EdiRound::create([
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'closes_at' => now()->addMonths(2)->toDateTimeString(),
            'name' => '07/2026', 'note' => '',
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

    public function test_pasma_trend_aggregates_band_share_by_distinct_stations(): void
    {
        // Pásma i kategorie jsou předvyplněné kanonickým číselníkem (viz TestCase);
        // band_id 1 = 144 MHz, 2 = 432 MHz (EdiBand::CANONICAL).
        $kat144 = $this->category(1, 'SO', 'domestic');
        $kat144mo = $this->category(1, 'MO', 'domestic'); // jiná kategorie téhož pásma
        $kat432 = $this->category(2, 'SO', 'domestic');

        $kolo1 = EdiRound::create(['starts_at' => '2026-02-15', 'closes_at' => '2026-02-20 23:59:59', 'name' => '02/2026', 'note' => '', 'evaluated_at' => '2026-02-21 10:00:00']);
        $kolo2 = EdiRound::create(['starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59', 'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00']);

        // kolo1: 144 = OK1A ve dvou kategoriích téhož pásma (distinct 1) + OK1B
        // = 2 stanice; 432 = OK1C = 1.
        $this->bandEntry($kolo1, $kat144, 'OK1A');
        $this->bandEntry($kolo1, $kat144mo, 'OK1A');
        $this->bandEntry($kolo1, $kat144, 'OK1B');
        $this->bandEntry($kolo1, $kat432, 'OK1C');
        // kolo2: 144 = OK1A = 1; 432 = OK1B, OK1C, OK1D = 3.
        $this->bandEntry($kolo2, $kat144, 'OK1A');
        $this->bandEntry($kolo2, $kat432, 'OK1B');
        $this->bandEntry($kolo2, $kat432, 'OK1C');
        $this->bandEntry($kolo2, $kat432, 'OK1D');

        $trend = app(KoloStatistiky::class)->prehled($kolo2)['pasmaTrend'];

        $this->assertSame(['02/2026', '03/2026'], array_column($trend['rounds'], 'name'));
        $this->assertSame([2026, 2026], array_column($trend['rounds'], 'year'));
        // Pásma v kanonickém pořadí (band_id 1, 2).
        $this->assertSame(['144', '432'], array_column($trend['bands'], 'token'));
        // Počty různých značek na pásmu po kolech (distinct napříč duplicitami).
        $this->assertSame([2, 1], $trend['stanice'][0]); // 144 MHz
        $this->assertSame([1, 3], $trend['stanice'][1]); // 432 MHz
    }

    public function test_pasma_trend_renders_chart_on_round_page(): void
    {
        $kat = $this->category(1, 'SO', 'domestic');

        $kolo = EdiRound::create(['starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59', 'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00']);
        $this->bandEntry($kolo, $kat, 'OK1AAA');

        $this->get(route('statistiky.kolo', ['kolo' => $kolo->id]))
            ->assertOk()
            ->assertSee('chartPasma')
            ->assertSee('data-pasma-years="1"', false)
            ->assertSee(__('pages.stat.chart_pasma'));
    }

    /** Kategorie daného pásma/sekce/varianty z předseedovaného číselníku. */
    private function category(int $bandId, string $section, string $variant): EdiCategory
    {
        return EdiCategory::query()
            ->where('band_id', $bandId)
            ->where('section', $section)
            ->where('variant', $variant)
            ->firstOrFail();
    }

    /** Převzatý záznam listiny s danou kategorií (a tím pásmem) pro daný callsign. */
    private function bandEntry(EdiRound $kolo, EdiCategory $kat, string $callsign): void
    {
        EdiEntry::create([
            'round_id' => $kolo->id, 'category_id' => $kat->id,
            'qrp' => false, 'lp' => false, 'callsign' => $callsign, 'locator' => 'JN79AA',
            'qso_count' => 1, 'qso_points' => 1, 'multiplier' => 1, 'points' => 1,
            'name' => 'Test', 'email' => 't@t.cz', 'phone' => '', 'note' => '',
            'soapbox' => '', 'ip' => '', 'edi_head_id' => null,
            'rank' => 1, 'approved' => true, 'session_id' => '',
        ]);
    }

    /**
     * Vyhodnocené kolo se dvěma deníky (OK5BIG pracován napříč oběma) a dvěma
     * převzatými záznamy listiny pro žebříčky/souhrn.
     */
    private function seedEvaluatedRound(): EdiRound
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00',
        ]);

        $headA = Edihead::create(['round_id' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1AAA', 'p_wwlo' => 'JN79', 'p_band' => '144 MHz', 'r_name' => 'A', 'r_emai' => 'a@a.cz', 's_powe' => 100]);
        $headB = Edihead::create(['round_id' => $kolo->id, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89', 'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100]);

        foreach (['0810', '0811', '0812'] as $t) {
            Ediline::create(['edihead_id' => $headA->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        }
        foreach (['0820', '0821'] as $t) {
            Ediline::create(['edihead_id' => $headB->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        }

        $kat = EdiCategory::create(['name' => '144 MHz', 'section' => 'SO', 'variant' => 'domestic']);

        foreach ([['OK1AAA', 5, 4, 80], ['OK1BBB', 3, 3, 30]] as [$znacka, $pocet, $multiplier, $body]) {
            EdiEntry::create([
                'round_id' => $kolo->id, 'category_id' => $kat->id,
                'qrp' => false, 'lp' => false, 'callsign' => $znacka, 'locator' => 'JN79AA',
                'qso_count' => $pocet, 'qso_points' => 20, 'multiplier' => $multiplier, 'points' => $body,
                'name' => 'Test', 'email' => 't@t.cz', 'phone' => '', 'note' => '',
                'soapbox' => '', 'ip' => '', 'edi_head_id' => null,
                'rank' => 1, 'approved' => true, 'session_id' => '',
            ]);
        }

        return $kolo;
    }

    private function createApprovedEntry(EdiRound $kolo, string $callsign = 'OK1AAA'): void
    {
        EdiEntry::create([
            'round_id' => $kolo->id,
            'category_id' => null,
            'qrp' => false,
            'lp' => false,
            'callsign' => $callsign,
            'locator' => 'JN79AA',
            'qso_count' => 1,
            'qso_points' => 1,
            'multiplier' => 1,
            'points' => 1,
            'name' => 'Test',
            'email' => 'test@example.test',
            'phone' => '',
            'note' => '',
            'soapbox' => '',
            'ip' => '',
            'edi_head_id' => null,
            'rank' => 1,
            'approved' => true,
            'session_id' => '',
        ]);
    }
}
