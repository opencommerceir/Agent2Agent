<?php

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Domain\Entities\PointTransaction as PointTransactionEntity;
use App\Modules\Loyalty\Domain\Repositories\PointTransactionRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\TransactionType;
use App\Modules\Loyalty\Infrastructure\Models\PointTransaction as PointTransactionModel;
use DateTimeImmutable;

class EloquentPointTransactionRepository implements PointTransactionRepositoryInterface
{
    public function listByAccount(int $loyaltyAccountId, int $tenantId, int $limit): array
    {
        return PointTransactionModel::query()
            ->where('tenant_id', $tenantId)
            ->where('loyalty_account_id', $loyaltyAccountId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (PointTransactionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findExpirable(int $loyaltyAccountId, int $tenantId, DateTimeImmutable $asOf): array
    {
        $alreadyExpiredSourceIds = PointTransactionModel::query()
            ->where('loyalty_account_id', $loyaltyAccountId)
            ->where('transaction_type', TransactionType::Expire->value)
            ->whereNotNull('reference_id')
            ->pluck('reference_id');

        return PointTransactionModel::query()
            ->where('tenant_id', $tenantId)
            ->where('loyalty_account_id', $loyaltyAccountId)
            ->whereIn('transaction_type', [TransactionType::Earn->value, TransactionType::Bonus->value])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf)
            ->whereNotIn('id', $alreadyExpiredSourceIds)
            ->orderBy('id')
            ->get()
            ->map(fn (PointTransactionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(PointTransactionEntity $transaction): PointTransactionEntity
    {
        $model = new PointTransactionModel();
        $model->tenant_id = $transaction->tenantId();
        $model->loyalty_account_id = $transaction->loyaltyAccountId();
        $model->points = $transaction->points();
        $model->transaction_type = $transaction->transactionType()->value;
        $model->description = $transaction->description();
        $model->reference_id = $transaction->referenceId();
        $model->expires_at = $transaction->expiresAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PointTransactionModel $model): PointTransactionEntity
    {
        return new PointTransactionEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            loyaltyAccountId: $model->loyalty_account_id,
            points: $model->points,
            transactionType: TransactionType::from($model->transaction_type),
            description: $model->description,
            referenceId: $model->reference_id,
            expiresAt: $model->expires_at ? DateTimeImmutable::createFromInterface($model->expires_at) : null,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
