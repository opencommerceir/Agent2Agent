<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Domain\Exceptions\InsufficientInventoryException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;

/**
 * Mirrors Core's CheckPermissionAction: execute() is the primary query
 * (bool), authorize() is a throw-on-deny convenience wrapper for call
 * sites that want an assertion instead of an if. A product with no
 * Inventory record at all is treated as zero available (a conservative
 * default that blocks overselling untracked stock, rather than treating
 * "not tracked" as "unlimited").
 *
 * Two different questions, two different method pairs — do not merge
 * them back into one (HANDOFF §8.22 is the discovery record for why this
 * split exists):
 *
 * - execute()/authorize() ask "is there enough *uncommitted* capacity for
 *   a brand-new reservation" — checked against available()
 *   (quantityOnHand - quantityReserved). This is the right question for
 *   AddToCartAction, which is asking for a *new* hold on top of whatever
 *   is already reserved.
 * - executeCommit()/authorizeCommit() ask "can the quantity *already
 *   reserved* by this Cart still be fulfilled" — checked against
 *   quantityOnHand alone. This is the right question for PlaceOrderAction's
 *   re-check: that quantity was already subtracted into quantityReserved
 *   by AddToCartAction, so re-checking it against available() double-counts
 *   the very reservation being confirmed — for an Order of >= half of
 *   on-hand stock this made the re-check fail even though nothing was
 *   wrong. quantityOnHand is the correct, single-counted question to ask
 *   at commit time.
 *
 * $variantId (Phase 5, Stage 1 — Product Variants, §7.21) is an optional
 * trailing param on all four methods — null checks the parent Product's
 * own Inventory row, exactly as every caller did before this stage; a
 * real value checks that specific ProductVariant's own row instead.
 */
final class CheckInventoryAction
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $productId, int $tenantId, Quantity $requestedQuantity, ?int $variantId = null): bool
    {
        $inventory = $this->inventories->findByProduct($productId, $tenantId, $variantId);
        $available = $inventory ? $inventory->available() : 0;

        return $available >= $requestedQuantity->value();
    }

    public function authorize(int $productId, int $tenantId, Quantity $requestedQuantity, ?int $variantId = null): void
    {
        if (! $this->execute($productId, $tenantId, $requestedQuantity, $variantId)) {
            $subject = $variantId !== null ? "variant [{$variantId}]" : "product [{$productId}]";

            throw new InsufficientInventoryException(
                "Insufficient inventory for {$subject}: requested {$requestedQuantity->value()}."
            );
        }
    }

    public function executeCommit(int $productId, int $tenantId, Quantity $requestedQuantity, ?int $variantId = null): bool
    {
        $inventory = $this->inventories->findByProduct($productId, $tenantId, $variantId);
        $onHand = $inventory ? $inventory->quantityOnHand() : 0;

        return $onHand >= $requestedQuantity->value();
    }

    public function authorizeCommit(int $productId, int $tenantId, Quantity $requestedQuantity, ?int $variantId = null): void
    {
        if (! $this->executeCommit($productId, $tenantId, $requestedQuantity, $variantId)) {
            $subject = $variantId !== null ? "variant [{$variantId}]" : "product [{$productId}]";

            throw new InsufficientInventoryException(
                "Insufficient on-hand inventory to commit {$subject}: requested {$requestedQuantity->value()}."
            );
        }
    }
}
