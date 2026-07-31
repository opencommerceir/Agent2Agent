<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * WooCommerce's own numeric product id — distinct from this platform's own
 * auto-increment Product id. Kept as an explicit type rather than a bare
 * int so a WooCommerce id can never be silently passed where a local
 * Product id was expected, the same reasoning SKU/Money wrap their
 * primitives instead of staying plain scalars.
 */
final class WooCommerceProductId
{
    public function __construct(
        private readonly int $value,
    ) {
        if ($this->value <= 0) {
            throw new InvalidArgumentException(
                "Invalid WooCommerce product id [{$this->value}]. Expected a positive integer."
            );
        }
    }

    public function value(): int
    {
        return $this->value;
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
