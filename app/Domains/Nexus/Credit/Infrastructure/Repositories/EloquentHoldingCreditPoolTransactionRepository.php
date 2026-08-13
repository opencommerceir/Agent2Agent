<?php

namespace App\Domains\Nexus\Credit\Infrastructure\Repositories;

use App\Domains\Nexus\Credit\Domain\Entities\HoldingCreditPoolTransaction as HoldingCreditPoolTransactionEntity;
use App\Domains\Nexus\Credit\Domain\Repositories\HoldingCreditPoolTransactionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Credit\Infrastructure\Models\HoldingCreditPoolTransaction as HoldingCreditPoolTransactionModel;
use DateTimeImmutable;

class EloquentHoldingCreditPoolTransactionRepository implements HoldingCreditPoolTransactionRepositoryInterface
{
    public function findByHoldingId(int $holdingId): array
    {
        return HoldingCreditPoolTransactionModel::query()
            ->where('holding_id', $holdingId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (HoldingCreditPoolTransactionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(HoldingCreditPoolTransactionEntity $transaction): HoldingCreditPoolTransactionEntity
    {
        $model = new HoldingCreditPoolTransactionModel();
        $model->holding_id = $transaction->holdingId();
        $model->business_id = $transaction->businessId();
        $model->type = $transaction->type()->value;
        $model->amount = $transaction->amount();
        $model->reason = $transaction->reason();
        $model->balance_after = $transaction->balanceAfter();
        $model->related_id = $transaction->relatedId();
        $model->created_at = $transaction->createdAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(HoldingCreditPoolTransactionModel $model): HoldingCreditPoolTransactionEntity
    {
        return new HoldingCreditPoolTransactionEntity(
            id: $model->id,
            holdingId: $model->holding_id,
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
