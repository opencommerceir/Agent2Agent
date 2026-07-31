<?php

namespace App\Modules\CRM\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A Tag's display label. Trimmed and internal whitespace collapsed (so
 * "  VIP   Customer " and "VIP Customer" are the same tag) but casing is
 * preserved — unlike SKU/CouponCode, a Tag name is a human-facing label,
 * not a machine identifier, so forcing uppercase would be surprising.
 */
final class TagName
{
    private const MAX_LENGTH = 50;

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($normalized === '') {
            throw new InvalidArgumentException('Tag name cannot be empty.');
        }

        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            $max = self::MAX_LENGTH;

            throw new InvalidArgumentException(
                "Tag name [{$normalized}] exceeds the maximum length of {$max} characters."
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
