<?php

namespace App\Modules\Commerce\Domain\Events;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Carries only the identifiers, not the Product entity — the Product is
 * soft-deleted by the time this dispatches, and the entity itself adds
 * nothing a listener would need beyond "which product, in which tenant".
 */
final class ProductWasDeleted
{
    public function __construct(
        public readonly int $productId,
        public readonly int $tenantId,
    ) {
    }
}
