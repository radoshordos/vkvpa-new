<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ContestWindow;
use PHPUnit\Framework\TestCase;

class ContestWindowTest extends TestCase
{
    public function test_day_from_tdate_extracts_yymmdd(): void
    {
        $this->assertSame('260615', ContestWindow::dayFromTDate('20260615'));
        $this->assertSame('260615', ContestWindow::dayFromTDate('20260615;20260616'));
        $this->assertSame('260615', ContestWindow::dayFromTDate('  20260615  '));
    }

    public function test_day_from_tdate_returns_empty_for_short_input(): void
    {
        $this->assertSame('', ContestWindow::dayFromTDate(''));
        $this->assertSame('', ContestWindow::dayFromTDate('2026'));
    }
}
