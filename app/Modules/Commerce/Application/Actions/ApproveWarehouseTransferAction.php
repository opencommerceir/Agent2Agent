<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseTransferData;
use App\Modules\Commerce\Domain\Exceptions\InsufficientWarehouseStockException;
use App\Modules\Commerce\Domain\Exceptions\WarehouseTransferNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseTransferRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use Illuminate\Support\Facades\DB;

/**
 * Approving a transfer is the moment stock is actually put on hold at the
 * *source* Warehouse — reserve(), not commit(): the transfer hasn't
 * physically moved anything yet, it has only guaranteed the units it will
 * move can't be sold or pulled into a second transfer out from under it
 * (CompleteWarehouseTransferAction is what turns this hold into a real
 * stock reduction). Every item's Inventory row is row-locked
 * (findByProductForUpdate) inside one DB::transaction() for the same
 * reason AddToCartAction locks its own row: without it, two concurrent
 * Approve calls against overlapping stock could each pass the
 * availability check before either had committed its reservation.
 *
 * Insufficient stock is surfaced as this module's own
 * InsufficientWarehouseStockException (HTTP 409 via ConflictExceptionInterface)
 * rather than the lower-level Inventory::reserve()'s InsufficientInventoryException
 * — the same "translate to the caller's own domain exception"
 * reasoning CheckInventoryAction documents. Checking available() directly
 * before calling reserve() (rather than catching the entity's own
 * exception) also lets the message name the Warehouse a plain
 * InsufficientInventoryException has no idea about.
 *
 * WarehouseTransfer::approve()'s own illegal-transition guard is left to
 * throw its InvalidArgumentException unmodified if this transfer isn't
 * Pending — no special handling needed here.
 */
final class ApproveWarehouseTransferAction
{
    public function __construct(
        private readonly WarehouseTransferRepositoryInterface $transfers,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $id, int $tenantId, int $approvedBy): WarehouseTransferData
    {
        return DB::transaction(function () use ($id, $tenantId, $approvedBy) {
            $transfer = $this->transfers->findById($id, $tenantId);

            if (! $transfer) {
                throw new WarehouseTransferNotFoundException("WarehouseTransfer [{$id}] does not exist.");
            }

            foreach ($transfer->items() as $item) {
                $inventory = $this->inventories->findByProductForUpdate(
                    $item->productId(),
                    $tenantId,
                    $item->variantId(),
                    $transfer->sourceWarehouseId(),
                );

                $available = $inventory?->available() ?? 0;

                if ($available < $item->quantity()) {
                    $subject = $item->variantId() !== null ? "variant [{$item->variantId()}]" : "product [{$item->productId()}]";

                    throw new InsufficientWarehouseStockException(
                        "Only {$available} unit(s) of {$subject} available at warehouse [{$transfer->sourceWarehouseId()}], requested {$item->quantity()}."
                    );
                }

                $inventory->reserve(new Quantity($item->quantity()));
                $this->inventories->save($inventory);
            }

            $transfer->approve($approvedBy);
            $transfer = $this->transfers->save($transfer);

            return WarehouseTransferData::fromEntity($transfer);
        });
    }
}
