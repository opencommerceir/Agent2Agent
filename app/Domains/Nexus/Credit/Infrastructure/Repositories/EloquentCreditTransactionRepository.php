<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\CreditTransaction as CreditTransactionEntity;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Credit\Infrastructure\Models\CreditTransaction as CreditTransactionModel;
use DateTimeImmutable;

class EloquentCreditTransactionRepository implements CreditTransactionRepositoryInterface
{
    public function findByBusinessId(int $businessId): array
    {
        return CreditTransactionModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditTransactionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(CreditTransactionEntity $transaction): CreditTransactionEntity
    {
        $model = new CreditTransactionModel();
        $model->business_id = $transaction->businessId();
        $model->type = $transaction->type()->value;
        $model->amount = $transaction->amount();
        $model->reason = $transaction->reason();
        $model->balance_after = $transaction->balanceAfter();
        $model->related_id = $transaction->relatedId();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(CreditTransactionModel $model): CreditTransactionEntity
    {
        return new CreditTransactionEntity(
            id: $model->id,
            businessId: $model->business_id,
            type: CreditTransactionType::from($model->type),
            amount: $model->amount,
            reason: $model->reason,
            balanceAfter: $model->balance_after,
            relatedId: $model->related_id,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
