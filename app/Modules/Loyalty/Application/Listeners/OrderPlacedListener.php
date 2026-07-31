<?php

namespace App\Modules\Loyalty\Application\Listeners;

use App\Modules\Commerce\Domain\Events\OrderWasPlaced;
use App\Modules\Loyalty\Application\Actions\EarnPointsAction;
use App\Modules\Loyalty\Domain\Services\PointsCalculationService;

/**
 * The second real cross-module Domain Event Listener in this codebase
 * (after Workflows' InventoryLowListener) — reacts to Commerce's
 * `OrderWasPlaced` to earn points for the Order's Customer, if it has
 * one (rule §e: "Customer را از Order می‌گیرد").
 *
 * Unlike InventoryLowListener (which re-fetches Inventory through a
 * Repository because `InventoryWasCommitted` deliberately carries only
 * identifiers), `OrderWasPlaced` already carries the full, authoritative
 * Order entity — its `total()` and `customerId()` are exactly what's
 * needed, so re-fetching the Order through
 * `Commerce\Domain\Repositories\OrderRepositoryInterface` would be pure
 * redundancy. This is the same observation Workflows'
 * `HighValueOrderListener` docblock already made about this same event
 * (that Listener is scaffolding, unwired; this one is real) — reading
 * the entity a published Domain Event carries is not the forbidden kind
 * of cross-module coupling (HANDOFF §3 item 8's own distinction: reading
 * the returned/carried Entity is fine, importing the other module's
 * Model or Exception is not).
 *
 * Orders with no `customer_id` (Stage 4 made Customer optional on an
 * Order) and Orders worth less than $1 (rounds to 0 points,
 * PointsCalculationService's own floor-division docblock) are silently
 * skipped — neither is an error, both are simply "nothing to earn here".
 */
final class OrderPlacedListener
{
    public function __construct(
        private readonly PointsCalculationService $pointsCalculator,
        private readonly EarnPointsAction $earnPoints,
    ) {
    }

    public function handle(OrderWasPlaced $event): void
    {
        $order = $event->order;

        if ($order->customerId() === null) {
            return;
        }

        $points = $this->pointsCalculator->calculateForAmount($order->total()->amount());

        if ($points <= 0) {
            return;
        }

        $this->earnPoints->execute(
            tenantId: $order->tenantId(),
            customerId: $order->customerId(),
            points: $points,
            description: "Points earned from Order [{$order->orderNumber()}]",
            referenceId: $order->id(),
        );
    }
}
