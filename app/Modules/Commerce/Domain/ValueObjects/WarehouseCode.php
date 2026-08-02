<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use App\Modules\Commerce\Domain\Exceptions\InvalidWarehouseCodeException;

/**
 * Format: WH-XXXXX (5 uppercase alphanumeric characters), e.g. WH-TEHR1.
 * Caller-supplied (not auto-generated) — unlike TrackingNumber, an
 * operator naming their own warehouses wants recognizable codes
 * ("WH-TEHR1" for a Tehran warehouse), the same reasoning SKU is
 * caller-supplied rather than random. Uppercase-normalized on
 * construction so "wh-tehr1" and "WH-TEHR1" are the same code everywhere,
 * mirroring SKU's own normalization.
 */
final class WarehouseCode
{
    private const PATTERN = '/^WH-[A-Z0-9]{5}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidWarehouseCodeException(
                "Invalid warehouse code [{$value}]. Expected format: WH-XXXXX (5 letters/digits)."
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
