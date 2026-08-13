<?php

namespace App\Domains\Nexus\Approval\Infrastructure\Repositories;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalRequest as ApprovalRequestEntity;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalRequestRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalLevel;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalRequestStatus;
use App\Domains\Nexus\Approval\Infrastructure\Models\ApprovalRequest as ApprovalRequestModel;
use DateTimeImmutable;

class EloquentApprovalRequestRepository implements ApprovalRequestRepositoryInterface
{
    public function findById(int $id): ?ApprovalRequestEntity
    {
        $model = ApprovalRequestModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByNegotiationId(int $negotiationId): ?ApprovalRequestEntity
    {
        $model = ApprovalRequestModel::query()->where('negotiation_id', $negotiationId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(ApprovalRequestEntity $request): ApprovalRequestEntity
    {
        $model = $request->id()
            ? ApprovalRequestModel::query()->findOrFail($request->id())
            : new ApprovalRequestModel();

        $model->negotiation_id = $request->negotiationId();
        $model->business_id = $request->businessId();
        $model->required_levels = array_map(fn (ApprovalLevel $level) => $level->toArray(), $request->requiredLevels());
        $model->current_level_index = $request->currentLevelIndex();
        $model->status = $request->status()->value;
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ApprovalRequestModel $model): ApprovalRequestEntity
    {
        return new ApprovalRequestEntity(
            id: $model->id,
            negotiationId: $model->negotiation_id,
            businessId: $model->business_id,
            requiredLevels: array_map(fn (array $level) => ApprovalLevel::fromArray($level), $model->required_levels),
            currentLevelIndex: $model->current_level_index,
            status: ApprovalRequestStatus::from($model->status),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
