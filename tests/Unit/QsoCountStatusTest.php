<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\QsoCountStatus;
use PHPUnit\Framework\TestCase;

/**
 * Testy klasifikace započítání QSO ({@see QsoCountStatus}).
 */
class QsoCountStatusTest extends TestCase
{
    public function test_counts_complete_qso_in_window(): void
    {
        $status = QsoCountStatus::classify('59', '001', '0900', '260315', 'JN79', '260315', '0800', '1100');

        $this->assertSame(QsoCountStatus::Counted, $status);
        $this->assertTrue($status->isCounted());
    }

    public function test_missing_received_rst_is_incomplete(): void
    {
        $status = QsoCountStatus::classify('', '001', '0900', '260315', 'JN79', '260315', '0800', '1100');

        $this->assertSame(QsoCountStatus::IncompleteExchange, $status);
        $this->assertFalse($status->isCounted());
    }

    public function test_missing_received_number_is_incomplete(): void
    {
        $status = QsoCountStatus::classify('59', '', '0900', '260315', 'JN79', '260315', '0800', '1100');

        $this->assertSame(QsoCountStatus::IncompleteExchange, $status);
    }

    public function test_incomplete_takes_precedence_over_window(): void
    {
        // Mimo okno i neúplné → neúplný příjem je nejzávažnější (vyhodnotí se první).
        $status = QsoCountStatus::classify('', '', '2300', '260315', '', '260315', '0800', '1100');

        $this->assertSame(QsoCountStatus::IncompleteExchange, $status);
    }

    public function test_complete_but_out_of_window(): void
    {
        $status = QsoCountStatus::classify('59', '001', '2300', '260315', 'JN79', '260315', '0800', '1100');

        $this->assertSame(QsoCountStatus::OutOfWindow, $status);
    }

    public function test_complete_but_empty_locator(): void
    {
        $status = QsoCountStatus::classify('59', '001', '0900', '260315', '', '260315', '0800', '1100');

        $this->assertSame(QsoCountStatus::EmptyWwl, $status);
    }
}
