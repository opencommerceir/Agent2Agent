<?php

namespace App\Core\Domain\ValueObjects;

use App\Core\Domain\Exceptions\InvalidEmailException;

/**
 * Core's own Email VO — a deliberate duplicate of Commerce's
 * `App\Modules\Commerce\Domain\ValueObjects\Email`, not a shared/reused
 * class. Importing Commerce's concrete VO into Core would be exactly the
 * Core -> Module dependency CLAUDE.md forbids ("Core must never depend on
 * business domains") — the same reasoning every module-owns-its-own-Money
 * precedent (Finance §7.8, Shipping §7.12) already established, just
 * Core -> Module instead of Module -> Module. Normalized to lowercase on
 * construction, same reasoning Commerce's own Email VO already gives.
 */
final class Email
{
    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email address [{$value}].");
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
