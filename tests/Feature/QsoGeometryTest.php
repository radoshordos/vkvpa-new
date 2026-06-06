<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Edihead;
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
        $this->assertSame(1, $first->mode); // SSB
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
}
