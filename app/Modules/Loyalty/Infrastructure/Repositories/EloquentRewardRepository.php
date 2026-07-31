<?php

namespace App\Modules\Loyalty\Infrastructure\Repositories;

use App\Modules\Loyalty\Domain\Entities\Reward as RewardEntity;
use App\Modules\Loyalty\Domain\Repositories\RewardRepositoryInterface;
use App\Modules\Loyalty\Domain\ValueObjects\Points;
use App\Modules\Loyalty\Domain\ValueObjects\RewardType;
use App\Modules\Loyalty\Infrastructure\Models\Reward as RewardModel;
use DateTimeImmutable;

class EloquentRewardRepository implements RewardRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?RewardEntity
    {
        $model = RewardModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?bool $isActive): array
    {
        $builder = RewardModel::query()->where('tenant_id', $tenantId);

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (RewardModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(RewardEntity $reward): RewardEntity
    {
        $model = $reward->id()
            ? RewardModel::query()->where('tenant_id', $reward->tenantId())->findOrFail($reward->id())
            : new RewardModel();

        $model->tenant_id = $reward->tenantId();
        $model->name = $reward->name();
        $model->description = $reward->description();
        $model->reward_type = $reward->rewardType()->value;
        $model->points_required = $reward->pointsRequired()->value();
        $model->discount_amount = $reward->discountAmount();
        $model->is_active = $reward->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(RewardModel $model): RewardEntity
    {
        return new RewardEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            description: $model->description,
            rewardType: RewardType::from($model->reward_type),
            pointsRequired: new Points($model->points_required),
            discountAmount: $model->discount_amount,
            isActive: (bool) $model->is_active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
