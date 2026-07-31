<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

use App\Modules\Shipping\Domain\Exceptions\InvalidWeightException;

/**
 * A non-negative weight in grams — always an integer, never a fractional
 * gram (HANDOFF gotcha #4 territory, applied to a physical unit instead
 * of money). Zero is valid (a Product with no `weight_grams` attribute
 * set at all — see CreateShipmentAction's own docblock for why Product
 * has no first-class Weight field of its own yet).
 */
final class Weight
{
    private readonly int $grams;

    public function __construct(int $grams)
    {
        if ($grams < 0) {
            throw new InvalidWeightException("Weight cannot be negative, got [{$grams}] grams.");
        }

        $this->grams = $grams;
    }

    public function grams(): int
    {
        return $this->grams;
    }

    public function kilograms(): float
    {
        return $this->grams / 1000;
    }

    public function add(self $other): self
    {
        return new self($this->grams + $other->grams);
    }

    public function equals(self $other): bool
    {
        return $this->grams === $other->grams;
    }
}
