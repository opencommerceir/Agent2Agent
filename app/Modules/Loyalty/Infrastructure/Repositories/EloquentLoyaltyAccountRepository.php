<?php

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Domain\Entities\LoyaltyAccount as LoyaltyAccountEntity;
use App\Modules\Loyalty\Domain\Entities\Redemption as RedemptionEntity;
use App\Modules\Loyalty\Domain\Repositories\LoyaltyAccountRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Infrastructure\Models\LoyaltyAccount as LoyaltyAccountModel;
use App\Modules\Loyalty\Infrastructure\Models\Redemption as RedemptionModel;
use DateTimeImmutable;

class EloquentLoyaltyAccountRepository implements LoyaltyAccountRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?LoyaltyAccountEntity
    {
        $model = LoyaltyAccountModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByCustomer(int $customerId, int $tenantId): ?LoyaltyAccountEntity
    {
        $model = LoyaltyAccountModel::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function customerHasAccount(int $customerId, int $tenantId): bool
    {
        return LoyaltyAccountModel::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->exists();
    }

    public function allForTenant(int $tenantId): array
    {
        return LoyaltyAccountModel::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->map(fn (LoyaltyAccountModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(LoyaltyAccountEntity $account): LoyaltyAccountEntity
    {
        $model = $account->id()
            ? LoyaltyAccountModel::query()->where('tenant_id', $account->tenantId())->findOrFail($account->id())
            : new LoyaltyAccountModel();

        $model->tenant_id = $account->tenantId();
        $model->customer_id = $account->customerId();
        $model->total_points_earned = $account->totalPointsEarned()->value();
        $model->total_points_redeemed = $account->totalPointsRedeemed()->value();
        $model->current_balance = $account->currentBalance()->value();
        $model->save();

        return $this->toEntity($model);
    }

    public function saveRedemption(RedemptionEntity $redemption): RedemptionEntity
    {
        $model = new RedemptionModel();
        $model->tenant_id = $redemption->tenantId();
        $model->loyalty_account_id = $redemption->loyaltyAccountId();
        $model->reward_id = $redemption->rewardId();
        $model->points_used = $redemption->pointsUsed()->value();
        $model->status = $redemption->status();
        $model->save();

        return $this->toRedemptionEntity($model);
    }

    public function listRedemptions(int $loyaltyAccountId, int $tenantId, int $limit): array
    {
        return RedemptionModel::query()
            ->where('tenant_id', $tenantId)
            ->where('loyalty_account_id', $loyaltyAccountId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (RedemptionModel $model) => $this->toRedemptionEntity($model))
            ->all();
    }

    private function toEntity(LoyaltyAccountModel $model): LoyaltyAccountEntity
    {
        return new LoyaltyAccountEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            customerId: $model->customer_id,
            totalPointsEarned: new Points($model->total_points_earned),
            totalPointsRedeemed: new Points($model->total_points_redeemed),
            currentBalance: new Points($model->current_balance),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    private function toRedemptionEntity(RedemptionModel $model): RedemptionEntity
    {
        return new RedemptionEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            loyaltyAccountId: $model->loyalty_account_id,
            rewardId: $model->reward_id,
            pointsUsed: new Points($model->points_used),
            status: $model->status,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
