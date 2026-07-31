<?php

namespace App\Modules\Finance\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A tax jurisdiction code (e.g. "US-CA", "IR-TEH") — a 2-letter country
 * code, a dash, and a 2-4 letter subdivision code. Normalized to
 * uppercase, same reasoning SKU/CouponCode already established.
 *
 * `DEFAULT` is a second, reserved value outside that format — a tenant's
 * fallback tax rate when no rate is configured for the caller's specific
 * region. This is how CommerceTaxRateProvider's 3-tier fallback
 * (region-specific -> tenant default -> Commerce's own hardcoded 9%,
 * see that class's docblock) stays a first-class, documented concept
 * instead of a magic string scattered across Actions.
 */
final class TaxRegion
{
    public const DEFAULT_REGION = 'DEFAULT';

    private const PATTERN = '/^[A-Z]{2}-[A-Z]{2,4}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if ($normalized !== self::DEFAULT_REGION && ! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidArgumentException(
                "Invalid tax region [{$value}]. Expected format: XX-YYYY (e.g. US-CA) or the reserved value [".self::DEFAULT_REGION.']'
            );
        }

        $this->value = $normalized;
    }

    public static function default(): self
    {
        return new self(self::DEFAULT_REGION);
    }

    public function isDefault(): bool
    {
        return $this->value === self::DEFAULT_REGION;
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
