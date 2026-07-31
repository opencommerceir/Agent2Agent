<?php

namespace App\Modules\Loyalty\Domain\ValueObjects;

use DateTimeImmutable;

/**
 * When a batch of earned points stops being redeemable (rule §d.5:
 * "امتیازها بعد از ۱ سال منقضی می‌شوند (قابل تنظیم)"). Only ever
 * attached to an `earn`/`bonus` PointTransaction — `redeem`/`expire`/
 * `adjust` entries never expire, since they're already a permanent
 * subtraction, not a still-spendable balance.
 *
 * The "قابل تنظیم" (configurable) requirement is the optional
 * `$validityDays` parameter, not a config file — nothing in this stage
 * needs a *global*, deployment-wide override of the 365-day default; a
 * caller that does (e.g. a future per-Reward or per-tenant validity
 * period) passes a different value explicitly.
 */
final class ExpirationDate
{
    private const DEFAULT_VALIDITY_DAYS = 365;

    private function __construct(
        private readonly DateTimeImmutable $value,
    ) {
    }

    public static function from(DateTimeImmutable $issuedAt, int $validityDays = self::DEFAULT_VALIDITY_DAYS): self
    {
        return new self($issuedAt->modify("+{$validityDays} days"));
    }

    public function value(): DateTimeImmutable
    {
        return $this->value;
    }

    public function hasExpired(DateTimeImmutable $asOf): bool
    {
        return $asOf >= $this->value;
    }
}
