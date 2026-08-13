<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPool as HoldingCreditPoolEntity;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolRepositoryInterface;
use App\Domains\Nexus\Credit\Infrastructure\Models\HoldingCreditPool as HoldingCreditPoolModel;
use DateTimeImmutable;

class EloquentHoldingCreditPoolRepository implements HoldingCreditPoolRepositoryInterface
{
    public function findByHoldingId(int $holdingId): ?HoldingCreditPoolEntity
    {
        $model = HoldingCreditPoolModel::query()->where('holding_id', $holdingId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(HoldingCreditPoolEntity $pool): HoldingCreditPoolEntity
    {
        $model = $pool->id()
            ? HoldingCreditPoolModel::query()->findOrFail($pool->id())
            : new HoldingCreditPoolModel();

        $model->holding_id = $pool->holdingId();
        $model->balance = $pool->balance();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(HoldingCreditPoolModel $model): HoldingCreditPoolEntity
    {
        return new HoldingCreditPoolEntity(
            id: $model->id,
            holdingId: $model->holding_id,
            balance: $model->balance,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
