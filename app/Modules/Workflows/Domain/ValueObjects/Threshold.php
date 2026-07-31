<?php

namespace App\Modules\Workflows\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The numeric value a WorkflowRule's condition compares a matching
 * event's field against (e.g. 5, for "quantity_on_hand < 5"). A plain
 * non-negative integer wrapper — thresholds in this stage's only real
 * rule (Low Stock Alert) are always a stock count, never a fractional or
 * negative quantity.
 */
final class Threshold
{
    private readonly int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Threshold cannot be negative, got [{$value}].");
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
}
