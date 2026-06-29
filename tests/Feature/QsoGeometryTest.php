<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QsoMode;
use App\Models\EdiHead;
use App\Models\EdiLine;
use App\Models\EdiRound;
use App\Services\Edi\BigSquareCount;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\QsoGeometry;
use App\Support\Maidenhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Sdílená geometrie spojení pro mapy a vizualizaci.
 *
 * @see QsoGeometry
 */
class QsoGeometryTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): EdiHead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    public function test_enriched_qsos_compute_points_from_locators(): void
    {
        $head = $this->importSample();
        $home = Maidenhead::toLatLon((string) $head->p_wwlo); // JN99AJ

        $qsos = new QsoGeometry()->enrichedQsos($head, $home, 'qso_at');

        // sample.edi má 2 spojení, obě s platným lokátorem → obě se souřadnicemi.
        $this->assertCount(2, $qsos);

        $first = $qsos->firstWhere('call', 'OK2IMH');
        $this->assertInstanceOf(EnrichedQso::class, $first);
        // OK2IMH v JN99BP = vlastní velký čtverec JN99 → 2 body.
        $this->assertSame('JN99BP', $first->wwl);
        $this->assertSame(2, $first->points);
        $this->assertSame(QsoMode::Ssb, $first->mode);
        $this->assertSame(8 * 60, $first->timeMinutes); // 08:00

        $second = $qsos->firstWhere('call', 'OK2IWU');
        $this->assertInstanceOf(EnrichedQso::class, $second);
        // OK2IWU v JN89PV = sousední velký čtverec → 3 body.
        $this->assertSame(3, $second->points);
        $this->assertNotNull($second->dist);
        $this->assertNotNull($second->azimut);
    }

    public function test_enriched_qsos_without_home_have_null_distance(): void
    {
        $head = $this->importSample();

        $qsos = new QsoGeometry()->enrichedQsos($head, null, 'qso_at');

        $this->assertCount(2, $qsos);
        foreach ($qsos as $q) {
            $this->assertNull($q->dist);
            $this->assertNull($q->azimut);
        }
    }

    public function test_big_squares_aggregate_by_four_char_locator(): void
    {
        $head = $this->importSample();

        $squares = new QsoGeometry()->bigSquares($head);

        $bySquare = $squares->keyBy('square');
        $jn99 = $bySquare->get('JN99');
        $jn89 = $bySquare->get('JN89');

        $this->assertInstanceOf(BigSquareCount::class, $jn99);
        $this->assertInstanceOf(BigSquareCount::class, $jn89);
        $this->assertSame(1, $jn99->count);
        $this->assertSame(1, $jn89->count);
    }

    public function test_round_stations_aggregate_across_logs_and_filter_by_min_qso(): void
    {
        // Vyhodnocené kolo → cizí stanice z kola se smí zveřejnit.
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00',
        ]);
        $headA = $this->seedRoundLogs($kolo->id);

        $stations = new QsoGeometry()->roundStations($headA);

        // Jen OK5BIG (≥ 5 QSO); OK9SML vypadl pod prahem.
        $this->assertCount(1, $stations);

        $big = $stations->firstWhere('call', 'OK5BIG');
        $this->assertNotNull($big);
        $this->assertSame(5, $big['count']);
        $this->assertSame('JN99AA', $big['wwl']);
    }

    public function test_round_stations_hidden_while_round_still_open(): void
    {
        // Kolo v příjmu hlášení (uzávěrka v budoucnu, nevyhodnocené) → cizí
        // stanice se nesmí odhalit, i když by jinak prahem prošly.
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15 08:00:00', 'closes_at' => now()->addDay(),
            'name' => '03/2026', 'note' => '',
        ]);
        $headA = $this->seedRoundLogs($kolo->id);

        $this->assertCount(0, new QsoGeometry()->roundStations($headA));
    }

    public function test_round_stations_are_cached_per_round(): void
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00',
        ]);
        $headA = $this->seedRoundLogs($kolo->id);

        $this->assertCount(1, new QsoGeometry()->roundStations($headA));

        // Nová stanice nad prahem přidaná po prvním čtení – do vypršení TTL
        // se vrací cachovaná vrstva (po uzávěrce se data reálně nemění).
        foreach (['0830', '0831', '0832', '0833', '0834'] as $t) {
            EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK7NEW', 'received_wwl' => 'JO80AA']);
        }
        $this->assertCount(1, new QsoGeometry()->roundStations($headA));

        // Po zahození cache se vrstva přepočítá.
        Cache::flush();
        $this->assertCount(2, new QsoGeometry()->roundStations($headA));
    }

    public function test_compare_with_returns_unique_and_common_stations(): void
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00',
        ]);
        [$headA, $headB] = $this->seedCompareLogs($kolo->id);

        $cmp = new QsoGeometry()->compareWith($headA, $headB, Maidenhead::toLatLon('JN79DW'));

        $this->assertNotNull($cmp);

        // OK9SML udělal jen deník A, OK7UNI jen soupeř B, OK5BIG oba.
        // Vzájemná spojení (OK1AAA ↔ OK1BBB) se do porovnání nepočítají.
        $this->assertSame(['OK9SML'], array_column($cmp['onlyMine'], 'call'));
        $this->assertSame(['OK7UNI'], array_column($cmp['onlyRival'], 'call'));
        $this->assertSame(['OK5BIG'], array_column($cmp['both'], 'call'));

        // Vzdálenost i u soupeřovy stanice se počítá od domácího QTH deníku A.
        $this->assertNotNull($cmp['onlyRival'][0]['dist']);
        $this->assertSame('JO70AA', $cmp['onlyRival'][0]['wwl']);
    }

    public function test_compare_with_hidden_while_round_open(): void
    {
        // Kolo v příjmu hlášení → porovnání by odhalilo soupeřův deník, vrací null.
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15 08:00:00', 'closes_at' => now()->addDay(),
            'name' => '03/2026', 'note' => '',
        ]);
        [$headA, $headB] = $this->seedCompareLogs($kolo->id);

        $this->assertNull(new QsoGeometry()->compareWith($headA, $headB, null));
    }

    public function test_compare_with_requires_same_round(): void
    {
        $kolo = EdiRound::create([
            'starts_at' => '2026-03-15', 'closes_at' => '2026-03-20 23:59:59',
            'name' => '03/2026', 'note' => '', 'evaluated_at' => '2026-03-21 10:00:00',
        ]);
        [$headA, $headB] = $this->seedCompareLogs($kolo->id);
        $headB->update(['round_id' => null]);

        $this->assertNull(new QsoGeometry()->compareWith($headA, $headB, null));
        $this->assertNull(new QsoGeometry()->compareWith($headA, $headA, null));
    }

    /**
     * Dva deníky v jednom kole pro porovnání: OK5BIG udělali oba, OK9SML jen A,
     * OK7UNI jen B; navíc vzájemné spojení A↔B (z porovnání se vynechává).
     *
     * @return array{EdiHead, EdiHead}
     */
    private function seedCompareLogs(int $idKola): array
    {
        $headA = EdiHead::create(['round_id' => $idKola, 't_date' => '20260315', 'p_call' => 'OK1AAA', 'p_wwlo' => 'JN79', 'p_band' => '144 MHz', 'r_name' => 'A', 'r_emai' => 'a@a.cz', 's_powe' => 100]);
        $headB = EdiHead::create(['round_id' => $idKola, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89', 'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100]);

        EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 08:10:00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 08:15:00', 'call_sign' => 'OK9SML', 'received_wwl' => 'JO60AA']);
        EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 08:20:00', 'call_sign' => 'OK1BBB', 'received_wwl' => 'JN89AA']);

        EdiLine::create(['edi_head_id' => $headB->id, 'qso_at' => '2026-03-15 08:11:00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        EdiLine::create(['edi_head_id' => $headB->id, 'qso_at' => '2026-03-15 08:16:00', 'call_sign' => 'OK7UNI', 'received_wwl' => 'JO70AA']);
        EdiLine::create(['edi_head_id' => $headB->id, 'qso_at' => '2026-03-15 08:21:00', 'call_sign' => 'OK1AAA', 'received_wwl' => 'JN79AA']);

        return [$headA, $headB];
    }

    /**
     * Dva deníky v jednom kole: OK5BIG má 3 + 2 = 5 QSO v okně (projde),
     * OK9SML jen 1 (neprojde), jedno OK5BIG QSO je mimo závodní okno.
     * Vrací deník A.
     */
    private function seedRoundLogs(int $idKola): EdiHead
    {
        $headA = EdiHead::create(['round_id' => $idKola, 't_date' => '20260315', 'p_call' => 'OK1AAA', 'p_wwlo' => 'JN79', 'p_band' => '144 MHz', 'r_name' => 'A', 'r_emai' => 'a@a.cz', 's_powe' => 100]);
        $headB = EdiHead::create(['round_id' => $idKola, 't_date' => '20260315', 'p_call' => 'OK1BBB', 'p_wwlo' => 'JN89', 'p_band' => '144 MHz', 'r_name' => 'B', 'r_emai' => 'b@b.cz', 's_powe' => 100]);

        // OK5BIG: 3 QSO v deníku A + 2 v deníku B = 5 napříč kolem → projde (min 5).
        foreach (['0810', '0811', '0812'] as $t) {
            EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        }
        foreach (['0820', '0821'] as $t) {
            EdiLine::create(['edi_head_id' => $headB->id, 'qso_at' => '2026-03-15 '.substr($t, 0, 2).':'.substr($t, 2, 2).':00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);
        }
        // OK9SML: jen 1 QSO → neprojde.
        EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 08:15:00', 'call_sign' => 'OK9SML', 'received_wwl' => 'JO60AA']);
        // Mimo závodní okno → nezapočítá se (OK5BIG by jinak měl 6).
        EdiLine::create(['edi_head_id' => $headA->id, 'qso_at' => '2026-03-15 12:00:00', 'call_sign' => 'OK5BIG', 'received_wwl' => 'JN99AA']);

        return $headA;
    }
}
