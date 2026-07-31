<?php

namespace App\Modules\Finance\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Deliberately a separate class from
 * `App\Modules\Commerce\Domain\ValueObjects\Money`, not a reuse of it —
 * Finance depends on Commerce only through Commerce's own published
 * Repository Interfaces (Dependency Inversion, per this stage's explicit
 * rule), never through a shared Domain-layer class. A `Money` VO is small
 * and stable enough that duplicating it per-module costs little and keeps
 * both modules independently deployable — there is no Core-level or
 * shared-kernel home for it, and putting one there would mean Core
 * knowing about money, which CLAUDE.md's "Core must not know about ...
 * Payments" rule already forbids by the same logic.
 *
 * Same convention as Commerce's Money: amount is the smallest currency
 * unit (cents), never a float.
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
