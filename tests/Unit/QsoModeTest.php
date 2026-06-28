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
        $this->assertSame(QsoMode::SsbCw, QsoMode::fromCode(3));
        $this->assertSame(QsoMode::CwSsb, QsoMode::fromCode(4));
        $this->assertSame(QsoMode::Am, QsoMode::fromCode(5));
        $this->assertSame(QsoMode::Fm, QsoMode::fromCode(6));
    }

    public function test_codes_outside_allowed_range_map_to_other(): void
    {
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(0));
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(7));  // MGM – ve VKV PA nepovolené
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(8));  // SSTV
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(9));  // ATV
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(59)); // rozhozený sloupec (RST)
        $this->assertSame(QsoMode::Other, QsoMode::fromCode(99));
    }

    public function test_labels(): void
    {
        $this->assertSame('SSB', QsoMode::Ssb->label());
        $this->assertSame('CW', QsoMode::Cw->label());
        $this->assertSame('SSB/CW', QsoMode::SsbCw->label());
        $this->assertSame('CW/SSB', QsoMode::CwSsb->label());
        $this->assertSame('AM', QsoMode::Am->label());
        $this->assertSame('FM', QsoMode::Fm->label());
        $this->assertSame('?', QsoMode::Other->label());
    }
}
