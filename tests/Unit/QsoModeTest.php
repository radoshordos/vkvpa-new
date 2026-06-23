<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\QsoMode;
use PHPUnit\Framework\TestCase;

/**
 * @see QsoMode
 */
class QsoModeTest extends TestCase
{
    public function test_known_codes_map_to_modes(): void
    {
        $this->assertSame(QsoMode::Ssb, QsoMode::fromCode(1));
        $this->assertSame(QsoMode::Cw, QsoMode::fromCode(2));
        $this->assertSame(QsoMode::Mixed, QsoMode::fromCode(3));
        $this->assertSame(QsoMode::Am, QsoMode::fromCode(5));
        $this->assertSame(QsoMode::Fm, QsoMode::fromCode(6));
        $this->assertSame(QsoMode::Mgm, QsoMode::fromCode(7));
        $this->assertSame(QsoMode::Sstv, QsoMode::fromCode(8));
        $this->assertSame(QsoMode::Atv, QsoMode::fromCode(9));
    }

    public function test_unknown_or_missing_code_maps_to_other(): void
    {
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(0));
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(4)); // ve standardu nedefinováno
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(59)); // rozhozený sloupec (RST)
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(99));
    }

    public function test_labels(): void
    {
        $this->assertSame('SSB', QsoMode::Ssb->label());
        $this->assertSame('CW', QsoMode::Cw->label());
        $this->assertSame('FM', QsoMode::Fm->label());
        $this->assertSame('RTTY/MGM', QsoMode::Mgm->label());
        $this->assertSame('?', QsoMode::Other->label());
    }
}
