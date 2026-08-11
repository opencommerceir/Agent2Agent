<?php

namespace App\Domains\Nexus\Catalog\Infrastructure\Repositories;

use App\Domains\Nexus\Catalog\Domain\Entities\Service as ServiceEntity;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\ValueObjects\Money;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Service as ServiceModel;
use DateTimeImmutable;

class EloquentServiceRepository implements ServiceRepositoryInterface
{
    public function findById(int $id): ?ServiceEntity
    {
        $model = ServiceModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByBusinessId(int $businessId): array
    {
        return ServiceModel::query()
            ->where('business_id', $businessId)
            ->get()
            ->map(fn (ServiceModel $model) => $this->toEntity($model))
            ->all();
    }

    public function search(int $businessId, string $query): array
    {
        return ServiceModel::query()
            ->where('business_id', $businessId)
            ->where(fn ($q) => $q->where('name_fa', 'like', "%{$query}%")->orWhere('name_en', 'like', "%{$query}%"))
            ->get()
            ->map(fn (ServiceModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ServiceEntity $service): ServiceEntity
    {
        $model = $service->id()
            ? ServiceModel::query()->findOrFail($service->id())
            : new ServiceModel();

        $model->business_id = $service->businessId();
        $model->name_fa = $service->nameFa();
        $model->name_en = $service->nameEn();
        $model->price_amount = $service->hourlyPrice()->amount();
        $model->price_currency = $service->hourlyPrice()->currency();
        $model->duration_minutes = $service->durationMinutes();
        $model->attributes = $service->attributes();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ServiceModel $model): ServiceEntity
    {
        return new ServiceEntity(
            id: $model->id,
            businessId: $model->business_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            hourlyPrice: Money::fromAmount($model->price_amount, $model->price_currency),
            durationMinutes: $model->duration_minutes,
            attributes: $model->attributes,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
