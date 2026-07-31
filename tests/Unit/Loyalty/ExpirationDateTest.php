<?php

namespace Tests\Unit\Loyalty;

use App\Modules\Loyalty\Domain\ValueObjects\ExpirationDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExpirationDateTest extends TestCase
{
    public function test_from_withDefaultValidity_isOneYearLater(): void
    {
        $issuedAt = new DateTimeImmutable('2026-01-01');

        $expiration = ExpirationDate::from($issuedAt);

        $this->assertSame('2027-01-01', $expiration->value()->format('Y-m-d'));
    }

    public function test_from_withCustomValidityDays_respectsIt(): void
    {
        $issuedAt = new DateTimeImmutable('2026-01-01');

        $expiration = ExpirationDate::from($issuedAt, 30);

        $this->assertSame('2026-01-31', $expiration->value()->format('Y-m-d'));
    }

    public function test_hasExpired_beforeExpiryDate_returnsFalse(): void
    {
        $issuedAt = new DateTimeImmutable('2026-01-01');
        $expiration = ExpirationDate::from($issuedAt);

        $this->assertFalse($expiration->hasExpired(new DateTimeImmutable('2026-06-01')));
    }

    public function test_hasExpired_onOrAfterExpiryDate_returnsTrue(): void
    {
        $issuedAt = new DateTimeImmutable('2026-01-01');
        $expiration = ExpirationDate::from($issuedAt);

        $this->assertTrue($expiration->hasExpired(new DateTimeImmutable('2027-01-01')));
        $this->assertTrue($expiration->hasExpired(new DateTimeImmutable('2027-06-01')));
    }
}
