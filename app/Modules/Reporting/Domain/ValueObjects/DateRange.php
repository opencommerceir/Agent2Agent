<?php

namespace App\Modules\Reporting\Domain\ValueObjects;

use App\Modules\Reporting\Domain\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;
use Exception;

/**
 * An inclusive [start_date, end_date] window every report in this module
 * is scoped to. `start()` is normalized to 00:00:00 and `end()` to
 * 23:59:59 of the given calendar days — a caller passing
 * `start_date=2026-07-01, end_date=2026-07-31` means "all of July",
 * including whatever happened on the last day, not "up to midnight at
 * the start of July 31st" (the bug a naive `whereBetween` against
 * midnight-only timestamps would otherwise have).
 *
 * Parsing and ordering are both validated here, at construction — the
 * same "a Value Object's factory is the one place its invariant is
 * enforced" shape SKU/Email/Quantity already establish — so no
 * Query Builder or Domain Service downstream ever has to re-check that
 * `end` isn't before `start`.
 */
final class DateRange
{
    private function __construct(
        private readonly DateTimeImmutable $start,
        private readonly DateTimeImmutable $end,
    ) {
    }

    public static function fromStrings(string $startDate, string $endDate): self
    {
        try {
            $start = new DateTimeImmutable($startDate);
            $end = new DateTimeImmutable($endDate);
        } catch (Exception $e) {
            throw new InvalidDateRangeException("Invalid date: {$e->getMessage()}");
        }

        $start = $start->setTime(0, 0, 0);
        $end = $end->setTime(23, 59, 59);

        if ($end < $start) {
            throw new InvalidDateRangeException(
                "end_date [{$endDate}] cannot be before start_date [{$startDate}]."
            );
        }

        return new self($start, $end);
    }

    public function start(): DateTimeImmutable
    {
        return $this->start;
    }

    public function end(): DateTimeImmutable
    {
        return $this->end;
    }

    public function startDate(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function endDate(): string
    {
        return $this->end->format('Y-m-d');
    }
}
