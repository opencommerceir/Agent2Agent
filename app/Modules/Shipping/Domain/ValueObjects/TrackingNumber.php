<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Format: TRK-XXXXXXXX (8 random uppercase alphanumeric characters) —
 * unlike OrderNumber/InvoiceNumber, not date-based, since a tracking
 * number has no meaningful "which day" component to encode. No dedicated
 * exception class for a bad format (same call OrderNumber's own docblock
 * makes) — this VO is never constructed directly from untrusted Agent
 * input (no MCP capability accepts a raw tracking_number), only from
 * generate() or from a database row already known to be valid.
 */
final class TrackingNumber
{
    private const PATTERN = '/^TRK-[A-Z0-9]{8}$/';

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    private readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Invalid tracking number [{$value}]. Expected format: TRK-XXXXXXXX."
            );
        }

        $this->value = $value;
    }

    public static function generate(): self
    {
        $random = '';

        for ($i = 0; $i < 8; $i++) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return new self("TRK-{$random}");
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
