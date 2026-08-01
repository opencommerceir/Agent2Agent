<?php

namespace App\Core\Domain\ValueObjects;

/**
 * Wraps a bcrypt password hash. Deliberately uses PHP's own
 * password_hash()/password_verify() (bcrypt, PHP's default algo) rather
 * than Laravel's `Hash` facade — every Domain class in this codebase is
 * framework-free and testable with plain PHPUnit (PricingService,
 * WorkflowEvaluator, TemplateRenderer, ...); reaching for a Laravel facade
 * here would be the first Domain-layer exception to that rule for no real
 * benefit, since PHP's own password hashing functions already do exactly
 * this.
 *
 * Two named constructors, never a public plain constructor: fromPlainText()
 * hashes a new password (registration/change); fromHash() wraps an
 * already-hashed value read back from storage — conflating the two would
 * risk accidentally double-hashing a value that came from the database.
 */
final class HashedPassword
{
    private function __construct(
        private readonly string $hash,
    ) {
    }

    public static function fromPlainText(string $plainText): self
    {
        return new self(password_hash($plainText, PASSWORD_BCRYPT));
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(string $plainText): bool
    {
        return password_verify($plainText, $this->hash);
    }

    public function value(): string
    {
        return $this->hash;
    }
}
