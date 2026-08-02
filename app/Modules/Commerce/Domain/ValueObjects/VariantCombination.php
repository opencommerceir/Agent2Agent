<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * One not-yet-persisted attribute-value combination — e.g.
 * `{"Color": "Red", "Size": "L"}` — the shape
 * GenerateVariantCombinationsAction computes via a Cartesian product over
 * a set of VariantAttributes' own values, before each one becomes a real
 * ProductVariant. A pure value holder, nothing else — the same
 * "combines/represents, never fetches" shape every other VO in this
 * codebase already has.
 */
final class VariantCombination
{
    /**
     * @param array<string, string> $attributeValues attribute name => value, e.g. ['Color' => 'Red', 'Size' => 'L']
     */
    public function __construct(
        private readonly array $attributeValues,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function attributeValues(): array
    {
        return $this->attributeValues;
    }

    /**
     * @return list<string> ordered values only, for VariantSKU::generate()
     */
    public function skuSuffix(): array
    {
        return array_values($this->attributeValues);
    }
}
