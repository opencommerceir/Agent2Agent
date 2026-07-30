<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Format: ORD-YYYYMMDD-XXXXX. No dedicated exception class for a bad
 * format — same call as Money::fromAmount() in Stage 1: not every
 * format-constrained VO needs its own exception type, only the ones an
 * outer caller (an Action, MCPExceptionHandler) needs to distinguish by
 * catching a specific class.
 */
final class OrderNumber
{
    private const PATTERN = '/^ORD-\d{8}-\d{5}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Invalid order number [{$value}]. Expected format: ORD-YYYYMMDD-XXXXX."
            );
        }

        $this->value = $value;
    }

    public static function generate(DateTimeImmutable $date, int $sequence): self
    {
        $sequencePart = str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

        return new self(sprintf('ORD-%s-%s', $date->format('Ymd'), $sequencePart));
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
