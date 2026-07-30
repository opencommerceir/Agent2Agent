<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * amount is stored in the smallest currency unit (cents) — the same
 * convention UCPProduct::$priceAmount already uses — to avoid float
 * rounding errors across currencies (HANDOFF gotcha #4: JSON/PHP floats
 * are not exact).
 */
final class Money
{
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/';

    private function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
    }

    public static function fromAmount(int $amount, string $currency): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException("Money amount cannot be negative, got [{$amount}].");
        }

        $normalizedCurrency = strtoupper($currency);

        if (! preg_match(self::CURRENCY_PATTERN, $normalizedCurrency)) {
            throw new InvalidArgumentException(
                "Invalid currency code [{$currency}]. Expected a 3-letter ISO 4217 code (e.g. USD)."
            );
        }

        return new self($amount, $normalizedCurrency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return sprintf('%s %s', number_format($this->amount / 100, 2), $this->currency);
    }
}
