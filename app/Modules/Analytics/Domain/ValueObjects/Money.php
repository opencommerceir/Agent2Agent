<?php

namespace App\Modules\Analytics\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Deliberately a separate class from Commerce's/Finance's/Shipping's own
 * `Money` VOs, not a shared reuse — same reasoning Finance's own `Money`
 * docblock gives: a Money VO is small and stable enough that duplicating
 * it per-module costs little, and there is no Core-level shared kernel for
 * it (CLAUDE.md: "Core must not know about ... Payments"). Amount is the
 * smallest currency unit (cents), never a float — the same convention
 * every Money-shaped value in this codebase already follows.
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
