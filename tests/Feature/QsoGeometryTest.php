<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\QsoMode;
use App\Models\Edihead;
use App\Models\Ediline;
use App\Models\VkvpaKola;
use App\Services\Edi\BigSquareCount;
use App\Services\Edi\EdiImportService;
use App\Services\Edi\EdiParser;
use App\Services\Edi\EnrichedQso;
use App\Services\Edi\QsoGeometry;
use App\Support\Maidenhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sdílená geometrie spojení pro mapy a vizualizaci.
 *
 * @see QsoGeometry
 */
class QsoGeometryTest extends TestCase
{
    use RefreshDatabase;

    private function importSample(): Edihead
    {
        $edi = (string) file_get_contents(__DIR__.'/../fixtures/sample.edi');

        return new EdiImportService()->import(new EdiParser()->parse($edi));
    }

    public function test_enriched_qsos_compute_points_from_locators(): void
    {
        $head = $this->importSample();
        $home = Maidenhead::toLatLon((string) $head->PWWLo); // JN99AJ

        $qsos = new QsoGeometry()->enrichedQsos($head, $home, 'Time');

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

        $qsos = new QsoGeometry()->enrichedQsos($head, null, 'Time');

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
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15', 'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026', 'poznamka' => '', 'vyhodnoceno' => '2026-03-21 10:00:00',
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
        // Kolo v příjmu hlášení (aktivní, nevyhodnocené) → cizí stanice se
        // nesmí odhalit, i když by jinak prahem prošly.
        $kolo = VkvpaKola::create([
            'datum_konani' => '2026-03-15', 'datum_uzaverky' => '2026-03-20 23:59:59',
            'nazev' => '03/2026', 'poznamka' => '', 'aktivni' => true,
        ]);
        $headA = $this->seedRoundLogs($kolo->id);

        $this->assertCount(0, new QsoGeometry()->roundStations($headA));
    }

    /**
     * Dva deníky v jednom kole: OK5BIG má 3 + 2 = 5 QSO v okně (projde),
     * OK9SML jen 1 (neprojde), jedno OK5BIG QSO je mimo závodní okno.
     * Vrací deník A.
     */
    private function seedRoundLogs(int $idKola): Edihead
    {
        $headA = Edihead::create(['id_kola' => $idKola, 'TDate' => '20260315', 'PCall' => 'OK1AAA', 'PWWLo' => 'JN79', 'PBand' => '144 MHz', 'RName' => 'A', 'RHBBS' => 'a@a.cz', 'SPowe' => 100]);
        $headB = Edihead::create(['id_kola' => $idKola, 'TDate' => '20260315', 'PCall' => 'OK1BBB', 'PWWLo' => 'JN89', 'PBand' => '144 MHz', 'RName' => 'B', 'RHBBS' => 'b@b.cz', 'SPowe' => 100]);

        // OK5BIG: 3 QSO v deníku A + 2 v deníku B = 5 napříč kolem → projde (min 5).
        foreach (['0810', '0811', '0812'] as $t) {
            Ediline::create(['IDS' => $headA->ID, 'Date' => '260315', 'Time' => $t, 'CallSign' => 'OK5BIG', 'Received-WWL' => 'JN99AA']);
        }
        foreach (['0820', '0821'] as $t) {
            Ediline::create(['IDS' => $headB->ID, 'Date' => '260315', 'Time' => $t, 'CallSign' => 'OK5BIG', 'Received-WWL' => 'JN99AA']);
        }
        // OK9SML: jen 1 QSO → neprojde.
        Ediline::create(['IDS' => $headA->ID, 'Date' => '260315', 'Time' => '0815', 'CallSign' => 'OK9SML', 'Received-WWL' => 'JO60AA']);
        // Mimo závodní okno → nezapočítá se (OK5BIG by jinak měl 6).
        Ediline::create(['IDS' => $headA->ID, 'Date' => '260315', 'Time' => '1200', 'CallSign' => 'OK5BIG', 'Received-WWL' => 'JN99AA']);

        return $headA;
    }
}
