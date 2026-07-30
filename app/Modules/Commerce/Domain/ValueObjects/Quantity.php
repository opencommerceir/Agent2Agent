<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use App\Modules\Commerce\Domain\Exceptions\InvalidQuantityException;

/**
 * A strictly-positive count of units — a cart item or inventory
 * reservation can never be for zero or a negative quantity. Decreasing an
 * Inventory's reserved count towards zero is arithmetic Inventory itself
 * does directly (Domain\Entities\Inventory::release()), not modeled as a
 * Quantity, since "zero reserved" is a valid state a Quantity can never
 * represent.
 */
final class Quantity
{
    private readonly int $value;

    public function __construct(int $value)
    {
        if ($value <= 0) {
            throw new InvalidQuantityException("Quantity must be greater than zero, got [{$value}].");
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
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
