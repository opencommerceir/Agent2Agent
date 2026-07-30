<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use App\Modules\Commerce\Domain\Exceptions\InvalidCouponException;

/**
 * Format: COUPON-XXXXX (5 alphanumeric characters). Normalized to
 * uppercase on construction, same reasoning SKU/Email already
 * established for their own normalization.
 */
final class CouponCode
{
    private const PATTERN = '/^COUPON-[A-Z0-9]{5}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidCouponException(
                "Invalid coupon code [{$value}]. Expected format: COUPON-XXXXX (5 alphanumeric characters)."
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
