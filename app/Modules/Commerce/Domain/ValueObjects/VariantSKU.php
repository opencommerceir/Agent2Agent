<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

use App\Modules\Commerce\Domain\Exceptions\InvalidSKUException;

/**
 * A ProductVariant's own SKU — format `PARENT_SKU-ATTR1-ATTR2` (e.g.
 * `TSHIRT-RED-L`). A distinct type from `SKU` (per this stage's own
 * explicit request), but reuses `SKU`'s own validation pattern and throws
 * `InvalidSKUException` on an invalid value rather than inventing a
 * second, near-identical exception type for the same failure mode — a
 * generated `TSHIRT-RED-L` is exactly as "a SKU-shaped string" as
 * `TSHIRT` itself, just produced by a different path.
 */
final class VariantSKU
{
    private const PATTERN = '/^[A-Z0-9][A-Z0-9_-]{2,63}$/';

    private readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));

        if (! preg_match(self::PATTERN, $normalized)) {
            throw new InvalidSKUException(
                "Invalid variant SKU [{$value}]. Expected 3-64 characters: letters, digits, hyphens or underscores, starting with a letter or digit."
            );
        }

        $this->value = $normalized;
    }

    /**
     * @param list<string> $attributeValues ordered attribute values (e.g. ['Red', 'L'])
     */
    public static function generate(SKU $parentSku, array $attributeValues): self
    {
        $suffix = implode('-', array_map(strtoupper(...), $attributeValues));

        return new self("{$parentSku->value()}-{$suffix}");
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
