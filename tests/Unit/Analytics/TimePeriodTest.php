<?php

namespace Tests\Unit\Analytics;

use App\Modules\Analytics\Domain\ValueObjects\TimePeriod;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class TimePeriodTest extends TestCase
{
    public function test_daily_boundsForReturnsStartAndEndOfTheSameDay(): void
    {
        [$start, $end] = TimePeriod::Daily->boundsFor(new DateTimeImmutable('2026-07-15 14:30:00'));

        $this->assertSame('2026-07-15 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_monthly_boundsForReturnsFirstAndLastDayOfMonth(): void
    {
        [$start, $end] = TimePeriod::Monthly->boundsFor(new DateTimeImmutable('2026-02-15'));

        $this->assertSame('2026-02-01', $start->format('Y-m-d'));
        $this->assertSame('2026-02-28', $end->format('Y-m-d'));
    }

    public function test_yearly_boundsForReturnsJanuaryFirstToDecember31st(): void
    {
        [$start, $end] = TimePeriod::Yearly->boundsFor(new DateTimeImmutable('2026-07-15'));

        $this->assertSame('2026-01-01', $start->format('Y-m-d'));
        $this->assertSame('2026-12-31', $end->format('Y-m-d'));
    }

    public function test_weekly_boundsForReturnsMondayToSunday(): void
    {
        [$start, $end] = TimePeriod::Weekly->boundsFor(new DateTimeImmutable('2026-07-15')); // a Wednesday

        $this->assertSame('Monday', $start->format('l'));
        $this->assertSame('Sunday', $end->format('l'));
    }
}
