<?php

namespace App\Domains\Nexus\PrivateMarketplace\Infrastructure\Repositories;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplace as PrivateMarketplaceEntity;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\ValueObjects\PrivateMarketplaceStatus;
use App\Domains\Nexus\PrivateMarketplace\Infrastructure\Models\PrivateMarketplace as PrivateMarketplaceModel;
use DateTimeImmutable;

class EloquentPrivateMarketplaceRepository implements PrivateMarketplaceRepositoryInterface
{
    public function findById(int $id): ?PrivateMarketplaceEntity
    {
        $model = PrivateMarketplaceModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByOwnerBusinessId(int $ownerBusinessId): array
    {
        return PrivateMarketplaceModel::query()
            ->where('owner_business_id', $ownerBusinessId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PrivateMarketplaceModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(PrivateMarketplaceEntity $marketplace): PrivateMarketplaceEntity
    {
        $model = $marketplace->id()
            ? PrivateMarketplaceModel::query()->findOrFail($marketplace->id())
            : new PrivateMarketplaceModel();

        $model->owner_business_id = $marketplace->ownerBusinessId();
        $model->name_fa = $marketplace->nameFa();
        $model->name_en = $marketplace->nameEn();
        $model->branding_primary_color = $marketplace->brandingPrimaryColor();
        $model->status = $marketplace->status()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PrivateMarketplaceModel $model): PrivateMarketplaceEntity
    {
        return new PrivateMarketplaceEntity(
            id: $model->id,
            ownerBusinessId: $model->owner_business_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            brandingPrimaryColor: $model->branding_primary_color,
            status: PrivateMarketplaceStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
