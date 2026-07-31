<?php

namespace App\Modules\Commerce\Domain\Events;

use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after Inventory::commit() succeeds inside PlaceOrderAction
 * — the point where a soft Cart reservation becomes an actual stock
 * reduction (Inventory's own docblock). Not part of the original Phase 2
 * event set; added in Phase 3.3 (Workflows) because no event existed for
 * "stock actually went down," only `InventoryReserved` (the soft-hold
 * side) — the same "added unprompted because a real gap was found"
 * reasoning behind CRM's `TagNotFoundException` and Finance's
 * `OrderNotFoundException`. Carries only identifiers plus the committed
 * amount, not the Inventory entity itself — same reasoning
 * `InventoryReserved` already gives; a listener that needs the resulting
 * `quantityOnHand` fetches it fresh through `InventoryRepositoryInterface`
 * (Workflows' own `InventoryLowListener` does exactly this) rather than
 * trusting a snapshot that could already be stale by the time it runs.
 */
final class InventoryWasCommitted
{
    public function __construct(
        public readonly int $productId,
        public readonly int $tenantId,
        public readonly Quantity $quantity,
    ) {
    }
}
