<?php

namespace App\Modules\Analytics\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * The granularity a KPIValue/AnalyticsSnapshot is bucketed into.
 * `boundsFor()` is pure (stdlib `DateTimeImmutable` only, no framework
 * dependency, the same "framework-free Domain layer" rule every other
 * Calculator/Generator in this codebase already follows) — it computes
 * the calendar-aligned [start, end] window containing $reference, mirroring
 * Reporting's own `DateRange` normalization (00:00:00 start / 23:59:59 end)
 * so a caller never has to reason about inclusive/exclusive boundaries
 * itself.
 */
enum TimePeriod: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function boundsFor(DateTimeImmutable $reference): array
    {
        return match ($this) {
            self::Hourly => [
                $reference->setTime((int) $reference->format('H'), 0, 0),
                $reference->setTime((int) $reference->format('H'), 59, 59),
            ],
            self::Daily => [
                $reference->setTime(0, 0, 0),
                $reference->setTime(23, 59, 59),
            ],
            self::Weekly => [
                $reference->modify('monday this week')->setTime(0, 0, 0),
                $reference->modify('sunday this week')->setTime(23, 59, 59),
            ],
            self::Monthly => [
                $reference->modify('first day of this month')->setTime(0, 0, 0),
                $reference->modify('last day of this month')->setTime(23, 59, 59),
            ],
            self::Yearly => [
                $reference->setDate((int) $reference->format('Y'), 1, 1)->setTime(0, 0, 0),
                $reference->setDate((int) $reference->format('Y'), 12, 31)->setTime(23, 59, 59),
            ],
        };
    }
}
