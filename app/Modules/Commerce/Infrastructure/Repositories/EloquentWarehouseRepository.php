<?php

namespace App\Modules\Commerce\Infrastructure\Repositories;

use App\Modules\Commerce\Domain\Entities\Warehouse as WarehouseEntity;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseCode;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
use App\Modules\Commerce\Infrastructure\Models\Warehouse as WarehouseModel;
use DateTimeImmutable;

class EloquentWarehouseRepository implements WarehouseRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?WarehouseEntity
    {
        $model = WarehouseModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function codeExists(WarehouseCode $code, int $tenantId): bool
    {
        return WarehouseModel::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code->value())
            ->exists();
    }

    public function listByTenant(int $tenantId, ?bool $isActive = null): array
    {
        $builder = WarehouseModel::query()->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (WarehouseModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(WarehouseEntity $warehouse): WarehouseEntity
    {
        $model = $warehouse->id()
            ? WarehouseModel::query()->where('tenant_id', $warehouse->tenantId())->findOrFail($warehouse->id())
            : new WarehouseModel();

        $model->tenant_id = $warehouse->tenantId();
        $model->code = $warehouse->code()->value();
        $model->name = $warehouse->name();
        $model->address = $warehouse->location()->address;
        $model->latitude = $warehouse->location()->latitude;
        $model->longitude = $warehouse->location()->longitude;
        $model->is_active = $warehouse->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(WarehouseModel $model): WarehouseEntity
    {
        return new WarehouseEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            code: new WarehouseCode($model->code),
            name: $model->name,
            location: new WarehouseLocation($model->latitude, $model->longitude, $model->address),
            isActive: $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            updatedAt: DateTimeImmutable::createFromInterface($model->updated_at),
        );
    }
}
