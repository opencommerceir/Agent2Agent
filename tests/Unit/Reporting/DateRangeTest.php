<?php

namespace Tests\Unit\Reporting;

use App\Modules\Reporting\Domain\Exceptions\InvalidDateRangeException;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    public function test_fromStrings_normalizesStartToMidnight(): void
    {
        $range = DateRange::fromStrings('2026-07-01', '2026-07-31');

        $this->assertSame('2026-07-01 00:00:00', $range->start()->format('Y-m-d H:i:s'));
    }

    public function test_fromStrings_normalizesEndToEndOfDay(): void
    {
        $range = DateRange::fromStrings('2026-07-01', '2026-07-31');

        $this->assertSame('2026-07-31 23:59:59', $range->end()->format('Y-m-d H:i:s'));
    }

    public function test_fromStrings_withEndBeforeStart_throwsInvalidDateRangeException(): void
    {
        $this->expectException(InvalidDateRangeException::class);

        DateRange::fromStrings('2026-07-31', '2026-07-01');
    }

    public function test_fromStrings_withSameDay_succeeds(): void
    {
        $range = DateRange::fromStrings('2026-07-15', '2026-07-15');

        $this->assertSame('2026-07-15', $range->startDate());
        $this->assertSame('2026-07-15', $range->endDate());
    }

    public function test_fromStrings_withUnparseableDate_throwsInvalidDateRangeException(): void
    {
        $this->expectException(InvalidDateRangeException::class);

        DateRange::fromStrings('not-a-date', '2026-07-01');
    }
}
