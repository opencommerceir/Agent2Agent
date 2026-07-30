<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use App\Modules\Commerce\Domain\Exceptions\InvalidEmailException;

/**
 * Normalized to lowercase on construction so "Jane@Example.com" and
 * "jane@example.com" are treated as the same address everywhere
 * (uniqueness checks, lookups) — same reasoning SKU's uppercase
 * normalization already established.
 */
final class Email
{
    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email address [{$value}].");
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
