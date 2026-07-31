<?php

namespace App\Modules\Shipping\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Shipping's own Money — deliberately a second, separate class from
 * Commerce's, not a shared/reused one, the exact same reasoning
 * Finance's own `Money` docblock gives (HANDOFF §7.8): depending on
 * Commerce's Repository *Interfaces* is fine (Dependency Inversion), but
 * importing Commerce's concrete `Money` VO would be a direct Domain-layer
 * dependency on another module's class. Amount is stored in the
 * smallest currency unit (cents), never a float (HANDOFF gotcha #4).
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

    public function add(self $other): self
    {
        return self::fromAmount($this->amount + $other->amount, $this->currency);
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
