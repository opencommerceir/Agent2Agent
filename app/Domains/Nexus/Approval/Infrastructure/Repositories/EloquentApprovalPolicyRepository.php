<?php

namespace App\Domains\Nexus\Approval\Infrastructure\Repositories;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalPolicy as ApprovalPolicyEntity;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalPolicyRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use App\Domains\Nexus\Approval\Infrastructure\Models\ApprovalPolicy as ApprovalPolicyModel;
use DateTimeImmutable;

class EloquentApprovalPolicyRepository implements ApprovalPolicyRepositoryInterface
{
    public function findByBusinessId(int $businessId): ?ApprovalPolicyEntity
    {
        $model = ApprovalPolicyModel::query()->where('business_id', $businessId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(ApprovalPolicyEntity $policy): ApprovalPolicyEntity
    {
        $model = $policy->id()
            ? ApprovalPolicyModel::query()->findOrFail($policy->id())
            : new ApprovalPolicyModel();

        $model->business_id = $policy->businessId();
        $model->levels = array_map(fn (ApprovalLevel $level) => $level->toArray(), $policy->levels());
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ApprovalPolicyModel $model): ApprovalPolicyEntity
    {
        return new ApprovalPolicyEntity(
            id: $model->id,
            businessId: $model->business_id,
            levels: array_map(fn (array $level) => ApprovalLevel::fromArray($level), $model->levels),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
