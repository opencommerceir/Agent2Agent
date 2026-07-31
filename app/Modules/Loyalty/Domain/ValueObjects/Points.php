<?php

namespace App\Modules\Loyalty\Domain\ValueObjects;

use App\Modules\Loyalty\Domain\Exceptions\InvalidPointsException;

/**
 * A non-negative *amount* of points — how many points a LoyaltyAccount
 * carries in one of its running totals, how many a Reward costs, or how
 * many an Agent is asking to earn/redeem. Deliberately never negative
 * (unlike PointTransaction's own `points` column, a plain signed int —
 * see that Entity's own docblock for why the ledger's signed delta is
 * not this VO). Always an integer — a fractional point never exists
 * anywhere in this module (HANDOFF gotcha #4 territory, applied to a
 * loyalty count instead of a money amount).
 */
final class Points
{
    private readonly int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidPointsException("Points cannot be negative, got [{$value}].");
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
