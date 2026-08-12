<?php

namespace App\Domains\Nexus\Credit\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Credit's own copy — each module in this codebase owns its own Money VO
 * rather than sharing one (see Negotiation's own copy's docblock), since a
 * shared kernel type would itself be a direct inter-module dependency.
 * Same shape as every other copy: amount stored in the smallest currency
 * unit to avoid float rounding errors. Used only for the real-money side
 * of a credit purchase (CreditPurchaseSession's total) — the credit
 * balance itself (CreditBalance/CreditTransaction) is a plain integer
 * count of credits, not Money.
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
                "Invalid currency code [{$currency}]. Expected a 3-letter code (e.g. IRT)."
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
