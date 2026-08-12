<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\CreditBalance as CreditBalanceEntity;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Infrastructure\Models\CreditBalance as CreditBalanceModel;
use DateTimeImmutable;

class EloquentCreditBalanceRepository implements CreditBalanceRepositoryInterface
{
    public function findByBusinessId(int $businessId): ?CreditBalanceEntity
    {
        $model = CreditBalanceModel::query()->where('business_id', $businessId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(CreditBalanceEntity $balance): CreditBalanceEntity
    {
        $model = $balance->id()
            ? CreditBalanceModel::query()->findOrFail($balance->id())
            : new CreditBalanceModel();

        $model->business_id = $balance->businessId();
        $model->balance = $balance->balance();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CreditBalanceModel $model): CreditBalanceEntity
    {
        return new CreditBalanceEntity(
            id: $model->id,
            businessId: $model->business_id,
            balance: $model->balance,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
