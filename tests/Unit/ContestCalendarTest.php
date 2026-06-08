<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ContestCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContestCalendarTest extends TestCase
{
    #[DataProvider('thirdSundayProvider')]
    public function test_third_sunday_of(int $year, int $month, string $expected): void
    {
        $result = ContestCalendar::thirdSundayOf($year, $month);
        $this->assertSame($expected, $result->format('Y-m-d'));
        $this->assertSame(0, $result->dayOfWeek, 'Musí být neděle (dayOfWeek=0).');
    }

    /** @return array<string, array{int, int, string}> */
    public static function thirdSundayProvider(): array
    {
        return [
            'červen 2026' => [2026,  6, '2026-06-21'],
            'leden 2026' => [2026,  1, '2026-01-18'],
            'únor 2026' => [2026,  2, '2026-02-15'],
            'prosinec 2025' => [2025, 12, '2025-12-21'],
            'březen 2026' => [2026,  3, '2026-03-15'],
            'červenec 2026' => [2026,  7, '2026-07-19'],
        ];
    }

    public function test_round_start_is_at_0800_utc(): void
    {
        $start = ContestCalendar::roundStart(2026, 6);
        $this->assertSame('2026-06-21', $start->format('Y-m-d'));
        $this->assertSame('08:00:00', $start->format('H:i:s'));
        $this->assertSame('UTC', $start->timezoneName);
    }

    public function test_upload_deadline_is_following_friday(): void
    {
        // Neděle 21. 6. 2026 → pátek 26. 6. 2026
        $start = CarbonImmutable::parse('2026-06-21 08:00:00', 'UTC');
        $deadline = ContestCalendar::uploadDeadline($start);

        $this->assertSame('2026-06-26', $deadline->format('Y-m-d'));
        $this->assertSame('23:59:59', $deadline->format('H:i:s'));
        $this->assertSame(5, $deadline->dayOfWeek, 'Musí být pátek (dayOfWeek=5).');
    }

    public function test_upload_deadline_for_december(): void
    {
        // Neděle 21. 12. 2025 → pátek 26. 12. 2025
        $start = CarbonImmutable::parse('2025-12-21 08:00:00', 'UTC');
        $deadline = ContestCalendar::uploadDeadline($start);

        $this->assertSame('2025-12-26', $deadline->format('Y-m-d'));
        $this->assertSame(5, $deadline->dayOfWeek);
    }

    public function test_round_name_format(): void
    {
        $this->assertSame('06/2026', ContestCalendar::roundName(2026, 6));
        $this->assertSame('01/2026', ContestCalendar::roundName(2026, 1));
        $this->assertSame('12/2025', ContestCalendar::roundName(2025, 12));
    }
}
