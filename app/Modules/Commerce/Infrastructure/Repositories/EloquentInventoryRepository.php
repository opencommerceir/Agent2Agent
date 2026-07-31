<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Inventory as InventoryEntity;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Models\Inventory as InventoryModel;
use DateTimeImmutable;

class EloquentInventoryRepository implements InventoryRepositoryInterface
{
    public function findByProduct(int $productId, int $tenantId): ?InventoryEntity
    {
        $model = InventoryModel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByProductForUpdate(int $productId, int $tenantId): ?InventoryEntity
    {
        $model = InventoryModel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(InventoryEntity $inventory): InventoryEntity
    {
        $model = $inventory->id()
            ? InventoryModel::query()->where('tenant_id', $inventory->tenantId())->findOrFail($inventory->id())
            : InventoryModel::query()
                ->where('tenant_id', $inventory->tenantId())
                ->where('product_id', $inventory->productId())
                ->first() ?? new InventoryModel();

        $model->tenant_id = $inventory->tenantId();
        $model->product_id = $inventory->productId();
        $model->quantity_on_hand = $inventory->quantityOnHand();
        $model->quantity_reserved = $inventory->quantityReserved();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(InventoryModel $model): InventoryEntity
    {
        return new InventoryEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            productId: $model->product_id,
            quantityOnHand: $model->quantity_on_hand,
            quantityReserved: $model->quantity_reserved,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
