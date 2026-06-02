<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Maidenhead;
use PHPUnit\Framework\TestCase;

class MaidenheadTest extends TestCase
{
    public function test_converts_known_locator(): void
    {
        $c = Maidenhead::toLatLon('JN99AJ');

        $this->assertNotNull($c);
        $this->assertEqualsWithDelta(49.40, $c['lat'], 0.1);
        $this->assertEqualsWithDelta(18.04, $c['lon'], 0.1);
    }

    public function test_is_case_insensitive(): void
    {
        $this->assertEquals(
            Maidenhead::toLatLon('JN99AJ'),
            Maidenhead::toLatLon('jn99aj'),
        );
    }

    public function test_returns_null_for_invalid(): void
    {
        $this->assertNull(Maidenhead::toLatLon('XXXX'));
        $this->assertNull(Maidenhead::toLatLon('ZZ99ZZ')); // pole mimo A–R
        $this->assertNull(Maidenhead::toLatLon(''));
    }

    public function test_big_square_center(): void
    {
        $c = Maidenhead::bigSquareCenter('JN99');

        $this->assertNotNull($c);
        // JN99: lon = 9*20-180 + 9*2 + 1 = 19; lat = 13*10-90 + 9 + 0.5 = 49.5
        $this->assertEqualsWithDelta(19.0, $c['lon'], 0.001);
        $this->assertEqualsWithDelta(49.5, $c['lat'], 0.001);
    }

    public function test_big_square_center_rejects_invalid(): void
    {
        $this->assertNull(Maidenhead::bigSquareCenter('ZZ99'));   // pole mimo A–R
        $this->assertNull(Maidenhead::bigSquareCenter('JN9'));    // moc krátké
        $this->assertNull(Maidenhead::bigSquareCenter('JN99AJ')); // 6 znaků není velký čtverec
    }

    public function test_distance_km(): void
    {
        $this->assertEqualsWithDelta(0.0, Maidenhead::distanceKm(50.0, 15.0, 50.0, 15.0), 0.001);
        // 1° zeměpisné šířky ≈ 111 km.
        $this->assertEqualsWithDelta(111.0, Maidenhead::distanceKm(50.0, 15.0, 51.0, 15.0), 2.0);
    }

    public function test_bearing_deg(): void
    {
        // Přímo na sever ≈ 0°, na východ ≈ 90°.
        $this->assertEqualsWithDelta(0.0, Maidenhead::bearingDeg(50.0, 15.0, 51.0, 15.0), 0.5);
        $this->assertEqualsWithDelta(90.0, Maidenhead::bearingDeg(50.0, 15.0, 50.0, 16.0), 1.0);
    }
}
