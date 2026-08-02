<?php

namespace App\Modules\Commerce\Domain\Events;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Carries only the identifiers, not the ProductVariant entity — mirrors
 * ProductWasDeleted's own shape exactly: the variant is soft-deleted by
 * the time this dispatches, and the entity itself adds nothing a
 * listener would need beyond "which variant, of which product, in which
 * tenant".
 */
final class VariantWasDeleted
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $productId,
        public readonly int $tenantId,
    ) {
    }
}
