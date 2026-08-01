<?php

namespace App\Core\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * The calendar date an API version stops being served, carried on the
 * HTTP `Sunset` response header (RFC 8594) once it's deprecated. Wraps a
 * plain `DateTimeImmutable` — framework-free like every other Core VO —
 * and owns the one formatting rule that header actually requires
 * (IMF-fixdate, RFC 7231 §7.1.1.1, e.g. "Sat, 01 Jan 2028 00:00:00 GMT")
 * so no caller has to remember that format string itself.
 */
final class SunsetDate
{
    private readonly DateTimeImmutable $date;

    public function __construct(DateTimeImmutable $date)
    {
        $this->date = $date;
    }

    public static function fromString(string $date): self
    {
        return new self(new DateTimeImmutable($date));
    }

    public function toHttpDate(): string
    {
        return $this->date->format('D, d M Y H:i:s \G\M\T');
    }

    public function hasPassed(DateTimeImmutable $now): bool
    {
        return $this->date < $now;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }
}
