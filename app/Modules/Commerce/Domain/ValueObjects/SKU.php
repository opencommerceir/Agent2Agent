<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use App\Modules\Commerce\Domain\Exceptions\InvalidSKUException;

/**
 * A Stock Keeping Unit identifier. Normalized to uppercase on construction
 * so "abc-123" and "ABC-123" are treated as the same SKU everywhere
 * (repository lookups, uniqueness checks) without callers having to think
 * about casing.
 */
final class SKU
{
    private const PATTERN = '/^[A-Z0-9][A-Z0-9_-]{2,63}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidSKUException(
                "Invalid SKU [{$value}]. Expected 3-64 characters: letters, digits, hyphens or underscores, starting with a letter or digit."
            );
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
