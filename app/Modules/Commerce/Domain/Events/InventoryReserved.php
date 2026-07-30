<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after Inventory::reserve() succeeds. Carries only
 * identifiers plus the reserved amount, not the Inventory entity itself
 * — same reasoning as ProductWasDeleted: a listener needs "which product,
 * in which tenant, how much", nothing an entity reference adds.
 */
final class InventoryReserved
{
    public function __construct(
        public readonly int $productId,
        public readonly int $tenantId,
        public readonly Quantity $quantity,
    ) {
    }
}
