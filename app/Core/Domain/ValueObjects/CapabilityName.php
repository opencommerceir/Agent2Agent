<?php

namespace App\Core\Domain\ValueObjects;

use App\Core\Domain\Exceptions\InvalidCapabilityNameException;

/**
 * Enforces the "domain.resource.action" capability naming format
 * (e.g. commerce.product.search — MCP Tool Naming conventions).
 *
 * Deliberately a separate type from PermissionKey even though the format
 * regex is identical: a Capability ("a thing an Agent can invoke") and a
 * Permission ("the right to invoke it") are different concepts in the
 * ubiquitous language, and a required_permissions list frequently
 * references a *different* string than the capability's own name (e.g.
 * capability commerce.product.search requires permission
 * commerce.products.read). Merging the two types would make it easy to
 * accidentally pass one where the other belongs.
 */
final class CapabilityName
{
    private const PATTERN = '/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidCapabilityNameException(
                "Invalid capability name [{$value}]. Expected format: domain.resource.action (e.g. commerce.product.search)."
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
