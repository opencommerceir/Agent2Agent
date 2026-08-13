<?php

namespace App\Domains\Nexus\Holding\Infrastructure\Repositories;

use App\Domains\Nexus\Holding\Domain\Entities\Holding as HoldingEntity;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\ValueObjects\HoldingStatus;
use App\Domains\Nexus\Holding\Infrastructure\Models\Holding as HoldingModel;
use DateTimeImmutable;

class EloquentHoldingRepository implements HoldingRepositoryInterface
{
    public function findById(int $id): ?HoldingEntity
    {
        $model = HoldingModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByParentBusinessId(int $parentBusinessId): ?HoldingEntity
    {
        $model = HoldingModel::query()->where('parent_business_id', $parentBusinessId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(HoldingEntity $holding): HoldingEntity
    {
        $model = $holding->id()
            ? HoldingModel::query()->findOrFail($holding->id())
            : new HoldingModel();

        $model->parent_business_id = $holding->parentBusinessId();
        $model->name_fa = $holding->nameFa();
        $model->name_en = $holding->nameEn();
        $model->status = $holding->status()->value;
        $model->credit_pooling_enabled = $holding->creditPoolingEnabled();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(HoldingModel $model): HoldingEntity
    {
        return new HoldingEntity(
            id: $model->id,
            parentBusinessId: $model->parent_business_id,
            nameFa: $model->name_fa,
            nameEn: $model->name_en,
            status: HoldingStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            creditPoolingEnabled: (bool) $model->credit_pooling_enabled,
        );
    }
}
