<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A non-negative integer wrapper — higher applies first
 * (`DiscountRuleEvaluator`'s own ordering). The same "wrap a plain int
 * with the one rule it must obey" shape `Quantity`/`Threshold` already
 * establish, rather than a bare `int` DiscountRule's own constructor
 * would otherwise accept unchecked.
 */
final class DiscountPriority
{
    private readonly int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("DiscountPriority must be non-negative, got [{$value}].");
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function isHigherThan(self $other): bool
    {
        return $this->value > $other->value;
    }
}
