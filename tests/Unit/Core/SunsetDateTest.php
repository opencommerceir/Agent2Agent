<?php

namespace Tests\Unit\Core;

use App\Core\Domain\ValueObjects\SunsetDate;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Framework-free — wraps only PHP's own DateTimeImmutable, the same
 * "Core VO with no Laravel dependency" shape every other Domain VO has.
 */
class SunsetDateTest extends TestCase
{
    public function test_toHttpDate_formatsAsRfc7231ImfFixdate(): void
    {
        $sunset = new SunsetDate(new DateTimeImmutable('2028-01-01 00:00:00', new DateTimeZone('UTC')));

        $this->assertSame('Sat, 01 Jan 2028 00:00:00 GMT', $sunset->toHttpDate());
    }

    public function test_fromString_parsesADateOnlyStringAsMidnight(): void
    {
        $sunset = SunsetDate::fromString('2028-01-01');

        $this->assertSame('2028-01-01', $sunset->date()->format('Y-m-d'));
    }

    public function test_hasPassed_withDateInThePast_returnsTrue(): void
    {
        $sunset = SunsetDate::fromString('2020-01-01');

        $this->assertTrue($sunset->hasPassed(new DateTimeImmutable('2026-08-02')));
    }

    public function test_hasPassed_withDateInTheFuture_returnsFalse(): void
    {
        $sunset = SunsetDate::fromString('2028-01-01');

        $this->assertFalse($sunset->hasPassed(new DateTimeImmutable('2026-08-02')));
    }
}
