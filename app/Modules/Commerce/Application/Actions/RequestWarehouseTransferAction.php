<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WarehouseTransferData;
use App\Modules\Commerce\Domain\Entities\WarehouseTransfer;
use App\Modules\Commerce\Domain\Entities\WarehouseTransferItem;
use App\Modules\Commerce\Domain\Events\WarehouseTransferWasRequested;
use App\Modules\Commerce\Domain\Exceptions\WarehouseNotFoundException;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseTransferRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Opens a WarehouseTransfer in Pending status — no Inventory side effects
 * happen yet (rule §e.3: reservation only happens at Approve). Both
 * Warehouse ids are validated to exist for this tenant before the entity
 * is even constructed, since WarehouseTransfer itself has no way to know
 * whether a given int actually names a real Warehouse — source is checked
 * before destination so a request against two nonexistent ids always
 * names the source in its exception, a stable, predictable error rather
 * than whichever repository call happened to run first.
 *
 * WarehouseTransfer::request()'s own constructor guards (same source/
 * destination id, empty items) are left to surface unmodified — this
 * Action only adds the checks the entity has no way to perform itself.
 */
final class RequestWarehouseTransferAction
{
    public function __construct(
        private readonly WarehouseTransferRepositoryInterface $transfers,
        private readonly WarehouseRepositoryInterface $warehouses,
    ) {
    }

    /**
     * @param list<array{product_id: int, variant_id: ?int, quantity: int}> $items
     */
    public function execute(
        int $tenantId,
        int $sourceWarehouseId,
        int $destinationWarehouseId,
        int $requestedBy,
        array $items,
        ?string $notes = null,
    ): WarehouseTransferData {
        if (! $this->warehouses->findById($sourceWarehouseId, $tenantId)) {
            throw new WarehouseNotFoundException("Source warehouse [{$sourceWarehouseId}] does not exist.");
        }

        if (! $this->warehouses->findById($destinationWarehouseId, $tenantId)) {
            throw new WarehouseNotFoundException("Destination warehouse [{$destinationWarehouseId}] does not exist.");
        }

        $transferItems = array_map(
            fn (array $item) => new WarehouseTransferItem($item['product_id'], $item['variant_id'], $item['quantity']),
            $items,
        );

        $transfer = WarehouseTransfer::request(
            tenantId: $tenantId,
            sourceWarehouseId: $sourceWarehouseId,
            destinationWarehouseId: $destinationWarehouseId,
            requestedBy: $requestedBy,
            items: $transferItems,
            notes: $notes,
        );
        $transfer = $this->transfers->save($transfer);

        Event::dispatch(new WarehouseTransferWasRequested($transfer));

        return WarehouseTransferData::fromEntity($transfer);
    }
}
