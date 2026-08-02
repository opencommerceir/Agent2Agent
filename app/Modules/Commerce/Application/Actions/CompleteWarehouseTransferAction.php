<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseTransferData;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Events\WarehouseTransferWasCompleted;
use App\Modules\Commerce\Domain\Exceptions\WarehouseTransferNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseTransferRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Completing a transfer is where stock actually moves: the hold placed at
 * Approve time is turned into a real reduction at the source Warehouse
 * (commit(), the same operation PlaceOrderAction uses to convert a Cart's
 * soft reservation into a sale) and the same quantity lands as new
 * on-hand stock at the destination (receiveStock() — deliberately not
 * restore(), see Inventory::receiveStock()'s own docblock for why "stock
 * arriving for the first time" and "reversing a prior commit" are kept as
 * two different operations even though both simply add to
 * quantityOnHand).
 *
 * The source Inventory row is assumed to exist — Approve already reserved
 * against it, so its absence here would mean a row was deleted out from
 * under an in-flight transfer, a real bug rather than a normal business
 * outcome; this deliberately throws rather than silently treating a
 * missing row as "nothing to commit". The destination row, by contrast,
 * routinely does not exist yet (this may be the first time this Product
 * is ever stocked at that Warehouse) — Inventory::stock() constructs a
 * fresh zero-on-hand row for receiveStock() to add to, exactly the
 * pattern GetWarehouseStockAction's own "no row = zero" default mirrors
 * from the read side.
 *
 * Both row-locked lookups happen inside one DB::transaction() for the
 * same concurrent-safety reason ApproveWarehouseTransferAction locks its
 * own rows.
 */
final class CompleteWarehouseTransferAction
{
    public function __construct(
        private readonly WarehouseTransferRepositoryInterface $transfers,
        private readonly InventoryRepositoryInterface $inventories,
    ) {
    }

    public function execute(int $id, int $tenantId): WarehouseTransferData
    {
        return DB::transaction(function () use ($id, $tenantId) {
            $transfer = $this->transfers->findById($id, $tenantId);

            if (! $transfer) {
                throw new WarehouseTransferNotFoundException("WarehouseTransfer [{$id}] does not exist.");
            }

            foreach ($transfer->items() as $item) {
                $sourceInventory = $this->inventories->findByProductForUpdate(
                    $item->productId(),
                    $tenantId,
                    $item->variantId(),
                    $transfer->sourceWarehouseId(),
                );

                if (! $sourceInventory) {
                    $subject = $item->variantId() !== null ? "variant [{$item->variantId()}]" : "product [{$item->productId()}]";

                    throw new RuntimeException(
                        "No Inventory row for {$subject} at source warehouse [{$transfer->sourceWarehouseId()}] — it should have been reserved when this WarehouseTransfer was approved."
                    );
                }

                $sourceInventory->commit(new Quantity($item->quantity()));
                $this->inventories->save($sourceInventory);

                $destinationInventory = $this->inventories->findByProductForUpdate(
                    $item->productId(),
                    $tenantId,
                    $item->variantId(),
                    $transfer->destinationWarehouseId(),
                ) ?? Inventory::stock(
                    $tenantId,
                    $item->productId(),
                    0,
                    $item->variantId(),
                    $transfer->destinationWarehouseId(),
                );

                $destinationInventory->receiveStock($item->quantity());
                $this->inventories->save($destinationInventory);
            }

            $transfer->complete();
            $transfer = $this->transfers->save($transfer);

            Event::dispatch(new WarehouseTransferWasCompleted($transfer));

            return WarehouseTransferData::fromEntity($transfer);
        });
    }
}
