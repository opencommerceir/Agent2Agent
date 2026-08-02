<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductVariantData;
use App\Modules\Commerce\Domain\Entities\VariantAttributeValue;
use App\Modules\Commerce\Domain\Exceptions\DuplicateVariantException;
use App\Modules\Commerce\Domain\Exceptions\InvalidVariantCombinationException;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\VariantAttributeNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\VariantAttributeRepositoryInterface;

/**
 * The registry-driven counterpart to CreateProductVariantAction's own
 * free-form path (its own docblock) — every attribute/value here comes
 * from a real VariantAttribute/VariantAttributeValue row, ordered by
 * each attribute's own displayOrder (both the attributes themselves, in
 * the order $attributeIds names them, and each attribute's own values).
 *
 * Idempotent by design: composes CreateProductVariantAction per computed
 * combination (Actions composing Actions, HANDOFF §3 pattern #3) and
 * silently skips any combination that already exists
 * (DuplicateVariantException caught, not re-thrown) — re-running this
 * after adding a Product's own new attribute/value should only ever
 * create the genuinely new combinations, not fail on the ones already
 * generated. Only the newly-created variants are returned.
 */
final class GenerateVariantCombinationsAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly VariantAttributeRepositoryInterface $attributes,
        private readonly CreateProductVariantAction $createVariant,
    ) {
    }

    /**
     * @param list<int> $attributeIds
     * @return list<ProductVariantData>
     */
    public function execute(
        int $tenantId,
        int $productId,
        array $attributeIds,
        int $priceAmount,
        string $priceCurrency,
    ): array {
        if (! $this->products->findById($productId, $tenantId)) {
            throw new ProductNotFoundException("Product [{$productId}] does not exist.");
        }

        if ($attributeIds === []) {
            throw new InvalidVariantCombinationException('At least one attribute is required to generate variant combinations.');
        }

        $valuesByAttributeName = [];

        foreach ($attributeIds as $attributeId) {
            $attribute = $this->attributes->findById($attributeId, $tenantId);

            if (! $attribute) {
                throw new VariantAttributeNotFoundException("Variant attribute [{$attributeId}] does not exist.");
            }

            $valuesByAttributeName[$attribute->name()] = array_map(
                fn (VariantAttributeValue $value) => $value->value(),
                $attribute->values(),
            );
        }

        $created = [];

        foreach ($this->cartesianProduct($valuesByAttributeName) as $combination) {
            try {
                $created[] = $this->createVariant->execute(
                    tenantId: $tenantId,
                    productId: $productId,
                    attributes: $combination,
                    priceAmount: $priceAmount,
                    priceCurrency: $priceCurrency,
                );
            } catch (DuplicateVariantException) {
                continue; // already generated in a previous run — idempotent, not an error
            }
        }

        return $created;
    }

    /**
     * @param array<string, list<string>> $valuesByAttributeName
     * @return list<array<string, string>>
     */
    private function cartesianProduct(array $valuesByAttributeName): array
    {
        $combinations = [[]];

        foreach ($valuesByAttributeName as $attributeName => $values) {
            $expanded = [];

            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $expanded[] = $combination + [$attributeName => $value];
                }
            }

            $combinations = $expanded;
        }

        return $combinations;
    }
}
