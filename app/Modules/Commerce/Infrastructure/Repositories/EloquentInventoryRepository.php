<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Inventory as InventoryEntity;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Models\Inventory as InventoryModel;
use DateTimeImmutable;

class EloquentInventoryRepository implements InventoryRepositoryInterface
{
    public function findByProduct(int $productId, int $tenantId, ?int $variantId = null, ?int $warehouseId = null): ?InventoryEntity
    {
        $model = InventoryModel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByProductForUpdate(int $productId, int $tenantId, ?int $variantId = null, ?int $warehouseId = null): ?InventoryEntity
    {
        $model = InventoryModel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listLowStock(int $tenantId, int $threshold): array
    {
        return InventoryModel::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('(quantity_on_hand - quantity_reserved) < ?', [$threshold])
            ->get()
            ->map(fn (InventoryModel $model) => $this->toEntity($model))
            ->all();
    }

    public function listByProduct(int $productId, int $tenantId, ?int $variantId = null): array
    {
        return InventoryModel::query()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->get()
            ->map(fn (InventoryModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(InventoryEntity $inventory): InventoryEntity
    {
        $model = $inventory->id()
            ? InventoryModel::query()->where('tenant_id', $inventory->tenantId())->findOrFail($inventory->id())
            : InventoryModel::query()
                ->where('tenant_id', $inventory->tenantId())
                ->where('product_id', $inventory->productId())
                ->where('variant_id', $inventory->variantId())
                ->where('warehouse_id', $inventory->warehouseId())
                ->first() ?? new InventoryModel();

        $model->tenant_id = $inventory->tenantId();
        $model->product_id = $inventory->productId();
        $model->variant_id = $inventory->variantId();
        $model->warehouse_id = $inventory->warehouseId();
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
            variantId: $model->variant_id,
            warehouseId: $model->warehouse_id,
        );
    }
}
