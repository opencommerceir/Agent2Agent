<?php

namespace App\Modules\Workflows\Domain\ValueObjects;

/**
 * Which kind of external happening a Workflow reacts to.
 * `InventoryLow` is the only one with a real, wired Listener this stage
 * (`InventoryLowListener`, reacting to Commerce's `InventoryWasCommitted`)
 * — `CartAbandoned`/`OrderHighValue` are real, modeled values a
 * `workflow.definition.create` call can already register a Workflow
 * against, but nothing yet triggers them (see
 * CartAbandonedListener/HighValueOrderListener's own docblocks).
 */
enum EventType: string
{
    case InventoryLow = 'inventory_low';
    case CartAbandoned = 'cart_abandoned';
    case OrderHighValue = 'order_high_value';
}
