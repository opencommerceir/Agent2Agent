<?php

namespace App\Modules\Commerce\Application\DTOs;

/**
 * The Application-layer mirror of Domain\ValueObjects\VariantCombination
 * — used internally by GenerateVariantCombinationsAction to carry a
 * not-yet-persisted combination alongside its own pre-computed SKU
 * string before CreateProductVariantAction turns it into a real,
 * persisted ProductVariant.
 */
final class VariantCombinationData
{
    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly array $attributes,
        public readonly string $sku,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attributes' => $this->attributes,
            'sku' => $this->sku,
        ];
    }
}
