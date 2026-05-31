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
}
