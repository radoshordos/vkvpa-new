<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\QsoMode;
use App\Services\Edi\DenikStatistiky;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\PrefixResolver;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class DenikStatistikyTest extends TestCase
{
    private DenikStatistiky $svc;

    protected function setUp(): void
    {
        $this->svc = new DenikStatistiky;
    }

    // ---- minutes / hhmm --------------------------------------------------

    public function test_minutes_converts_hhmm(): void
    {
        $this->assertSame(0, DenikStatistiky::minutes('0000'));
        $this->assertSame(480, DenikStatistiky::minutes('0800'));
        $this->assertSame(660, DenikStatistiky::minutes('1100'));
        $this->assertSame(1439, DenikStatistiky::minutes('2359'));
    }

    public function test_hhmm_converts_minutes(): void
    {
        $this->assertSame('00:00', DenikStatistiky::hhmm(0));
        $this->assertSame('08:00', DenikStatistiky::hhmm(480));
        $this->assertSame('11:00', DenikStatistiky::hhmm(660));
        $this->assertSame('23:59', DenikStatistiky::hhmm(1439));
    }

    // ---- noveNasobice ----------------------------------------------------

    public function test_nove_multiplier_skips_home_square(): void
    {
        // QSO do vlastního čtverce nesmí tvořit nový násobič.
        $lines = new Collection([
            $this->qso('OK2IMH', 'JN99AJ', 2, 480),
        ]);

        $this->assertSame([], $this->svc->noveNasobice($lines, 'JN99'));
    }

    public function test_nove_multiplier_tracks_new_squares_in_order(): void
    {
        $lines = new Collection([
            $this->qso('OK2IMH', 'JN99AJ', 2, 480), // vlastní čtverec – nepočítá
            $this->qso('OK2IWU', 'JN89PV', 3, 481), // nový čtverec JN89
            $this->qso('OK1XYZ', 'JN79VS', 3, 482), // nový čtverec JN79
            $this->qso('OK2DDD', 'JN89AB', 3, 490), // JN89 již viděno – přeskočit
        ]);

        $result = $this->svc->noveNasobice($lines, 'JN99');

        $this->assertCount(2, $result);
        $this->assertSame('JN89', $result[0]['square']);
        $this->assertSame('OK2IWU', $result[0]['call']);
        $this->assertSame(2, $result[0]['poradi']); // 1=home JN99, 2=JN89
        $this->assertSame('JN79', $result[1]['square']);
        $this->assertSame(3, $result[1]['poradi']);
    }

    public function test_nove_multiplier_ignores_invalid_locators(): void
    {
        $lines = new Collection([
            $this->qso('OK1BAD', 'XXXX', 0, 480), // neplatný lokátor → přeskočit
            $this->qso('OK2IWU', 'JN89PV', 3, 481),
        ]);

        $result = $this->svc->noveNasobice($lines, 'JN99');

        $this->assertCount(1, $result);
        $this->assertSame('JN89', $result[0]['square']);
    }

    // ---- timeline --------------------------------------------------------

    public function test_timeline_labels_cover_window(): void
    {
        $result = $this->svc->timeline(new Collection, [], 480, 660);

        // 180 min / 15 = 12 intervalů
        $this->assertCount(12, $result['labels']);
        $this->assertSame('08:00', $result['labels'][0]);
        $this->assertSame('10:45', $result['labels'][11]);
    }

    public function test_timeline_places_qsos_into_correct_buckets(): void
    {
        $from = 480; // 08:00
        $to = 660;   // 11:00

        $lines = new Collection([
            $this->qso('A', 'JN99AJ', 2, 480), // bucket 0 (08:00)
            $this->qso('B', 'JN89PV', 3, 494), // bucket 0 (08:14)
            $this->qso('C', 'JO89AA', 3, 495), // bucket 1 (08:15)
        ]);

        $result = $this->svc->timeline($lines, [], $from, $to);

        $this->assertSame(2, $result['celkem'][0]);
        $this->assertSame(1, $result['celkem'][1]);
    }

    public function test_timeline_qso_at_window_end_goes_to_last_bucket(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN99AJ', 2, 660), // přesně na konci okna → poslední bucket
        ]);

        $result = $this->svc->timeline($lines, [], 480, 660);

        $this->assertSame(1, $result['celkem'][11]);
        $this->assertSame(0, $result['celkem'][0]);
    }

    public function test_timeline_counts_multiplier_in_separate_column(): void
    {
        $from = 480;
        $to = 660;

        $lines = new Collection([
            $this->qso('A', 'JN99AJ', 2, 480),
            $this->qso('B', 'JN89PV', 3, 496), // 08:16 → bucket 1
        ]);

        // JN89 je nový násobič v bucket 1 (t=496 → intdiv(16,15)=1).
        $multiplier = [
            ['square' => 'JN89', 'call' => 'B', 'cas' => '08:16', 't' => 496, 'poradi' => 2, 'idx' => 1],
        ];

        $result = $this->svc->timeline($lines, $multiplier, $from, $to);

        $this->assertSame(1, $result['celkem'][0]); // A v bucket 0
        $this->assertSame(1, $result['celkem'][1]); // B v bucket 1
        $this->assertSame(0, $result['nove'][0]);   // žádný násobič v bucket 0
        $this->assertSame(1, $result['nove'][1]);   // 1 nový násobič v bucket 1
    }

    // ---- azimuthRose ----------------------------------------------------

    public function test_azimuth_rose_has_16_sectors(): void
    {
        $result = $this->svc->azimuthRose(new Collection);

        $this->assertCount(16, $result['pocet']);
        $this->assertCount(16, $result['km']);
        $this->assertCount(16, $result['body']);
    }

    public function test_azimuth_rose_counts_correct_sectors(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480, 200, 0),   // sever (0°) → sektor 0 (S)
            $this->qso('B', 'JN79VS', 3, 481, 300, 90),  // východ (90°) → sektor 4 (V)
            $this->qso('C', 'JO89AA', 3, 482, null, null), // bez azimutu → přeskočit
        ]);

        $result = $this->svc->azimuthRose($lines);

        $this->assertSame(1, $result['pocet'][0]); // sektor S
        $this->assertSame(1, $result['pocet'][4]); // sektor V
        $this->assertSame(200, $result['km'][0]);
        $this->assertSame(3, $result['body'][0]);
    }

    // ---- bodyPodleCtvercu -----------------------------------------------

    public function test_body_podle_ctvercu_aggregates_and_sorts(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480),
            $this->qso('B', 'JN89AA', 3, 481), // stejný čtverec JN89
            $this->qso('C', 'JN99AJ', 2, 482),
        ]);

        $result = $this->svc->bodyPodleCtvercu($lines);

        // JN89: 2 QSO × 3 b. = 6 → první; JN99: 1 QSO × 2 b. = 2 → druhý
        $this->assertCount(2, $result);
        $this->assertSame('JN89', $result[0]['square']);
        $this->assertSame(2, $result[0]['pocet']);
        $this->assertSame(6, $result[0]['body']);
        $this->assertSame('JN99', $result[1]['square']);
    }

    public function test_body_podle_ctvercu_ignores_invalid_locators(): void
    {
        $lines = new Collection([
            $this->qso('A', 'XXXX', 0, 480), // neplatný → přeskočit
            $this->qso('B', 'JN89PV', 3, 481),
        ]);

        $result = $this->svc->bodyPodleCtvercu($lines);

        $this->assertCount(1, $result);
        $this->assertSame('JN89', $result[0]['square']);
    }

    // ---- topOdx ---------------------------------------------------------

    public function test_top_odx_returns_top_n_by_distance(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480, 200),
            $this->qso('B', 'JN79VS', 3, 481, 500),
            $this->qso('C', 'JO89AA', 3, 482, 150),
        ]);

        $result = $this->svc->topOdx($lines, 2);

        $this->assertCount(2, $result);
        $this->assertSame('B', $result[0]['call']);
        $this->assertSame(500, $result[0]['dist']);
    }

    public function test_top_odx_excludes_qsos_without_distance(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480, null), // chybí vzdálenost
            $this->qso('B', 'JN79VS', 3, 481, 500),
        ]);

        $result = $this->svc->topOdx($lines);

        $this->assertCount(1, $result);
        $this->assertSame('B', $result[0]['call']);
    }

    public function test_top_odx_includes_cas_and_mode(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480, 200, null, QsoMode::Cw),
        ]);

        $result = $this->svc->topOdx($lines);

        $this->assertSame('08:00', $result[0]['cas']);
        $this->assertSame('CW', $result[0]['mode']);
    }

    // ---- modeStats ------------------------------------------------------

    public function test_mode_stats_groups_by_mode(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480, 200, null, QsoMode::Ssb),
            $this->qso('B', 'JN79VS', 3, 481, 300, null, QsoMode::Ssb),
            $this->qso('C', 'JO89AA', 4, 482, null, null, QsoMode::Cw),
        ]);

        $result = $this->svc->modeStats($lines);

        $this->assertCount(2, $result);

        $ssb = collect($result)->firstWhere('label', 'SSB');
        $this->assertNotNull($ssb);
        $this->assertSame(2, $ssb['pocet']);
        $this->assertSame(6, $ssb['body']);
        $this->assertSame(250, $ssb['avgDist']); // (200+300)/2
        $this->assertSame(300, $ssb['maxDist']);

        $cw = collect($result)->firstWhere('label', 'CW');
        $this->assertNotNull($cw);
        $this->assertSame(1, $cw['pocet']);
        $this->assertSame(0, $cw['avgDist']); // dist=null → 0

        $this->assertSame(QsoMode::Ssb->value, $ssb['mode']);
        $this->assertSame(QsoMode::Cw->value, $cw['mode']);
    }

    public function test_mode_stats_orders_canonically_with_other_last(): void
    {
        $lines = new Collection([
            $this->qso('A', 'JN89PV', 3, 480, 100, null, QsoMode::Other),
            $this->qso('B', 'JN79VS', 3, 481, 100, null, QsoMode::Cw),
            $this->qso('C', 'JO89AA', 4, 482, 100, null, QsoMode::Ssb),
        ]);

        $modes = array_column($this->svc->modeStats($lines), 'mode');

        // 1 (SSB) a 2 (CW) vzestupně, 0 (Ostatní) až na konci.
        $this->assertSame([QsoMode::Ssb->value, QsoMode::Cw->value, QsoMode::Other->value], $modes);
    }

    // ---- podleZemi / podlePrefixu ---------------------------------------

    public function test_podle_zemi_aggregates_sorts_and_buckets_unknown(): void
    {
        $resolver = new PrefixResolver([
            ['prefix' => 'OK', 'country' => 'Czech Rep'],
            ['prefix' => 'OL', 'country' => 'Czech Rep'],
            ['prefix' => '9A', 'country' => 'Croatia'],
        ]);

        $lines = new Collection([
            $this->qso('OK1ABC', 'JN89', 3, 480),
            $this->qso('OL2XYZ', 'JN79', 3, 481), // taky Czech Rep
            $this->qso('9A1AA', 'JN95', 3, 482),
            $this->qso('XYZ9', 'JO60', 3, 483),   // neznámý prefix → Ostatní
        ]);

        $result = $this->svc->podleZemi($lines, $resolver);

        // Czech Rep 2 (OK+OL) první; Croatia 1 a Ostatní 1 mají shodu → abecedně.
        $this->assertSame(
            [
                ['country' => 'Czech Rep', 'pocet' => 2],
                ['country' => 'Croatia', 'pocet' => 1],
                ['country' => 'Ostatní', 'pocet' => 1],
            ],
            $result,
        );
    }

    public function test_podle_prefixu_keeps_prefix_granularity(): void
    {
        $resolver = new PrefixResolver([
            ['prefix' => 'OK', 'country' => 'Czech Rep'],
            ['prefix' => 'OL', 'country' => 'Czech Rep'],
        ]);

        $lines = new Collection([
            $this->qso('OK1ABC', 'JN89', 3, 480),
            $this->qso('OK2DEF', 'JN79', 3, 481),
            $this->qso('OL3GHI', 'JN95', 3, 482),
        ]);

        $result = $this->svc->podlePrefixu($lines, $resolver);

        // OK 2 první; OL 1 druhý (na rozdíl od podleZemi se OK a OL neslučují).
        $this->assertSame(
            [
                ['prefix' => 'OK', 'pocet' => 2],
                ['prefix' => 'OL', 'pocet' => 1],
            ],
            $result,
        );
    }

    public function test_podle_zemi_empty_lines_returns_empty(): void
    {
        $resolver = new PrefixResolver([['prefix' => 'OK', 'country' => 'Czech Rep']]);

        $this->assertSame([], $this->svc->podleZemi(new Collection, $resolver));
    }

    // ---- tempo ----------------------------------------------------------

    public function test_tempo_computes_peak_hour_and_pause(): void
    {
        // 3 QSO v 08:00–08:20 (špička), pak pauza 100 min do 10:00.
        $lines = new Collection([
            $this->qso('A', 'JN89', 3, 480), // 08:00
            $this->qso('B', 'JN79', 3, 490), // 08:10
            $this->qso('C', 'JO89', 3, 500), // 08:20
            $this->qso('D', 'JO79', 3, 600), // 10:00
        ]);

        $result = $this->svc->tempo($lines, 480, 660);

        $this->assertSame(3, $result['spickaQso']);
        $this->assertSame(100, $result['pauza']);   // 600 − 500 = 100 min
        $this->assertNotNull($result['pauzaKdy']);
        $this->assertStringContainsString('08:20', (string) $result['pauzaKdy']);
        $this->assertStringContainsString('10:00', (string) $result['pauzaKdy']);
    }

    public function test_tempo_with_empty_lines_returns_nulls(): void
    {
        $result = $this->svc->tempo(new Collection, 480, 660);

        $this->assertNull($result['spicka']);
        $this->assertSame(0, $result['spickaQso']);
        $this->assertNull($result['pauza']);
        $this->assertNull($result['pauzaKdy']);
        $this->assertSame(0.0, $result['prumer']);
    }

    public function test_tempo_average_qso_per_hour(): void
    {
        // 4 QSO v 3hodinovém okně = 1.3/h
        $lines = new Collection([
            $this->qso('A', 'JN89', 3, 480),
            $this->qso('B', 'JN79', 3, 510),
            $this->qso('C', 'JO89', 3, 540),
            $this->qso('D', 'JO79', 3, 600),
        ]);

        $result = $this->svc->tempo($lines, 480, 660);

        $this->assertEqualsWithDelta(4 / 3, $result['prumer'], 0.05);
    }

    // ---- helper ---------------------------------------------------------

    private function qso(
        string $call,
        string $wwl,
        int $points,
        int $timeMinutes,
        ?int $dist = null,
        ?int $azimut = null,
        QsoMode $mode = QsoMode::Ssb,
    ): EnrichedQso {
        return new EnrichedQso(
            lat: 50.0,
            lon: 15.0,
            call: $call,
            wwl: $wwl,
            points: $points,
            dist: $dist,
            azimut: $azimut,
            timeMinutes: $timeMinutes,
            mode: $mode,
        );
    }
}
