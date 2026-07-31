<?php

namespace App\Modules\Workflows\Application\Listeners;

use App\Modules\Commerce\Domain\Events\InventoryWasCommitted;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Workflows\Application\Actions\TriggerWorkflowAction;
use App\Modules\Workflows\Domain\ValueObjects\EventType;

/**
 * The one real, wired Listener this stage — reacts to Commerce's
 * `InventoryWasCommitted` (dispatched by PlaceOrderAction, added this
 * stage specifically because no event existed for "stock actually went
 * down" — see that event's own docblock) and translates it into an
 * `inventory_low` Workflow trigger.
 *
 * Uses Commerce's `InventoryRepositoryInterface`/`ProductRepositoryInterface`
 * — Interfaces, never Commerce's Infrastructure/Model classes — the same
 * Dependency Inversion direction CRM/Finance already established for
 * Module -> Module (per this stage's explicit rule). Re-fetches the
 * Inventory's current `quantityOnHand` rather than trusting any snapshot
 * on the event itself, since `InventoryWasCommitted` deliberately carries
 * only identifiers (that event's own docblock) — the authoritative
 * current value only exists in the Repository.
 */
final class InventoryLowListener
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
        private readonly ProductRepositoryInterface $products,
        private readonly TriggerWorkflowAction $triggerWorkflow,
    ) {
    }

    public function handle(InventoryWasCommitted $event): void
    {
        $inventory = $this->inventories->findByProduct($event->productId, $event->tenantId);

        if (! $inventory) {
            return;
        }

        $product = $this->products->findById($event->productId, $event->tenantId);

        $this->triggerWorkflow->execute($event->tenantId, EventType::InventoryLow->value, [
            'product_id' => $event->productId,
            'quantity_on_hand' => $inventory->quantityOnHand(),
            'name' => $product?->name() ?? "Product #{$event->productId}",
        ]);
    }
}
