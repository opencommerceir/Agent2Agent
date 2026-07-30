<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A percentage (0-100), not a monetary amount — the "Financial Accuracy:
 * use Money, not float" rule targets money amounts specifically; a rate
 * like 8.25% cannot be represented without a fractional component.
 * PricingService rounds to whole cents the moment this rate is applied
 * to a Money amount, so no float ever survives into a stored amount.
 */
final class TaxRate
{
    private readonly float $value;

    public function __construct(float $value)
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException("Tax rate must be between 0 and 100, got [{$value}].");
        }

        $this->value = $value;
    }

    public function value(): float
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
