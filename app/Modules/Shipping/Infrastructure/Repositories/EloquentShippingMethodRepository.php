<?php

namespace App\Modules\Shipping\Infrastructure\Repositories;

use App\Modules\Shipping\Domain\Entities\ShippingMethod as ShippingMethodEntity;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\Money;
use App\Modules\Shipping\Infrastructure\Models\ShippingMethod as ShippingMethodModel;
use DateTimeImmutable;

class EloquentShippingMethodRepository implements ShippingMethodRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?ShippingMethodEntity
    {
        $model = ShippingMethodModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?bool $isActive): array
    {
        $builder = ShippingMethodModel::query()->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (ShippingMethodModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ShippingMethodEntity $method): ShippingMethodEntity
    {
        $model = $method->id()
            ? ShippingMethodModel::query()->where('tenant_id', $method->tenantId())->findOrFail($method->id())
            : new ShippingMethodModel();

        $model->tenant_id = $method->tenantId();
        $model->name = $method->name();
        $model->description = $method->description();
        $model->base_rate_amount = $method->baseRate()->amount();
        $model->base_rate_currency = $method->baseRate()->currency();
        $model->rate_per_kg_amount = $method->ratePerKg()->amount();
        $model->rate_per_kg_currency = $method->ratePerKg()->currency();
        $model->rate_per_km = $method->ratePerKm()->amount();
        $model->estimated_days_min = $method->estimatedDaysMin();
        $model->estimated_days_max = $method->estimatedDaysMax();
        $model->is_active = $method->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ShippingMethodModel $model): ShippingMethodEntity
    {
        return new ShippingMethodEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            description: $model->description,
            baseRate: Money::fromAmount($model->base_rate_amount, $model->base_rate_currency),
            ratePerKg: Money::fromAmount($model->rate_per_kg_amount, $model->rate_per_kg_currency),
            estimatedDaysMin: $model->estimated_days_min,
            estimatedDaysMax: $model->estimated_days_max,
            isActive: (bool) $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            ratePerKm: $model->rate_per_km !== null
                ? Money::fromAmount($model->rate_per_km, $model->base_rate_currency)
                : null,
        );
    }
}
