<?php

namespace App\Modules\Finance\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Format: INV-YYYYMMDD-XXXXX. Mirrors Commerce's OrderNumber exactly —
 * a random 5-digit suffix + collision check + retry (CreateInvoiceAction),
 * not a sequential counter, same reasoning OrderNumber's own docblock
 * gives.
 */
final class InvoiceNumber
{
    private const PATTERN = '/^INV-\d{8}-\d{5}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Invalid invoice number [{$value}]. Expected format: INV-YYYYMMDD-XXXXX."
            );
        }

        $this->value = $value;
    }

    public static function generate(DateTimeImmutable $date, int $sequence): self
    {
        $sequencePart = str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        return new self(sprintf('INV-%s-%s', $date->format('Ymd'), $sequencePart));
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
