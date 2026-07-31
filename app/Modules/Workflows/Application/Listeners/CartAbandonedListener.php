<?php

namespace App\Modules\Workflows\Application\Listeners;

use App\Modules\Commerce\Domain\Events\CartWasAbandoned;
use App\Modules\Workflows\Application\Actions\TriggerWorkflowAction;
use App\Modules\Workflows\Domain\ValueObjects\EventType;

/**
 * Wired for real now that the scheduling mechanism (HANDOFF §8.23/§8.27)
 * exists: MarkCartsAbandonedAction (the scheduled
 * `commerce:check-abandoned-carts` command) dispatches Commerce's new
 * `CartWasAbandoned` event, which this Listener reacts to — the same
 * "Actions composing Actions" translation into a `cart_abandoned`
 * Workflow trigger `InventoryLowListener` already established for
 * `inventory_low`. `EventType::CartAbandoned` has been a real, usable
 * value since Phase 3.3 (a Workflow could already be created against it
 * via `workflow.definition.create`) — this is the first thing that ever
 * actually triggers one.
 */
final class CartAbandonedListener
{
    public function __construct(
        private readonly TriggerWorkflowAction $triggerWorkflow,
    ) {
    }

    public function handle(CartWasAbandoned $event): void
    {
        $this->triggerWorkflow->execute($event->tenantId, EventType::CartAbandoned->value, [
            'cart_id' => $event->cartId,
            'owner_type' => $event->ownerType->value,
            'owner_id' => $event->ownerId,
        ]);
    }
}
