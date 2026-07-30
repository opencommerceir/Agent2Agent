<?php

namespace App\Core\Domain\ValueObjects;

use App\Core\Domain\Exceptions\InvalidPermissionKeyException;

/**
 * Enforces the platform-wide "domain.resource.action" capability naming
 * format (e.g. commerce.products.read — see MCP Tool Naming conventions).
 * A Value Object rather than a plain string so that an invalid key can
 * never exist anywhere in the system once constructed.
 */
final class PermissionKey
{
    private const PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidPermissionKeyException(
                "Invalid permission key [{$value}]. Expected format: domain.resource.action (e.g. commerce.products.read)."
            );
        }

        $this->value = $value;
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
