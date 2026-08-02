<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\WarehouseTransfer as WarehouseTransferEntity;
use App\Modules\Commerce\Domain\Entities\WarehouseTransferItem;
use App\Modules\Commerce\Domain\Repositories\WarehouseTransferRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\TransferStatus;
use App\Modules\Commerce\Infrastructure\Models\WarehouseTransfer as WarehouseTransferModel;
use App\Modules\Commerce\Infrastructure\Models\WarehouseTransferItem as WarehouseTransferItemModel;
use DateTimeImmutable;

/**
 * Never deletes-and-reinserts items, and only ever inserts them once (when
 * $isNew) — WarehouseTransferItem rows are immutable, the same pattern
 * EloquentInvoiceRepository already establishes for InvoiceItem.
 */
class EloquentWarehouseTransferRepository implements WarehouseTransferRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?WarehouseTransferEntity
    {
        $model = WarehouseTransferModel::query()->with('items')->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(WarehouseTransferEntity $transfer): WarehouseTransferEntity
    {
        $isNew = $transfer->id() === null;

        $model = $isNew
            ? new WarehouseTransferModel()
            : WarehouseTransferModel::query()->where('tenant_id', $transfer->tenantId())->findOrFail($transfer->id());

        $model->tenant_id = $transfer->tenantId();
        $model->source_warehouse_id = $transfer->sourceWarehouseId();
        $model->destination_warehouse_id = $transfer->destinationWarehouseId();
        $model->status = $transfer->status()->value;
        $model->requested_by = $transfer->requestedBy();
        $model->approved_by = $transfer->approvedBy();
        $model->completed_at = $transfer->completedAt();
        $model->notes = $transfer->notes();
        $model->save();

        if ($isNew) {
            foreach ($transfer->items() as $item) {
                $model->items()->create([
                    'product_id' => $item->productId(),
                    'variant_id' => $item->variantId(),
                    'quantity' => $item->quantity(),
                    'created_at' => now(),
                ]);
            }
        }

        return $this->toEntity($model->fresh('items'));
    }

    private function toEntity(WarehouseTransferModel $model): WarehouseTransferEntity
    {
        $items = $model->items->map(fn (WarehouseTransferItemModel $itemModel) => new WarehouseTransferItem(
            productId: $itemModel->product_id,
            variantId: $itemModel->variant_id,
            quantity: $itemModel->quantity,
        ))->all();

        return new WarehouseTransferEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            sourceWarehouseId: $model->source_warehouse_id,
            destinationWarehouseId: $model->destination_warehouse_id,
            status: TransferStatus::from($model->status),
            requestedBy: $model->requested_by,
            approvedBy: $model->approved_by,
            completedAt: $model->completed_at ? DateTimeImmutable::createFromInterface($model->completed_at) : null,
            notes: $model->notes,
            items: $items,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }
}
