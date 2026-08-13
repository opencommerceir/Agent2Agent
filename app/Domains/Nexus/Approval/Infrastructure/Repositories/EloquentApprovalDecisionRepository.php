<?php

namespace App\Domains\Nexus\Approval\Infrastructure\Repositories;

use App\Domains\Nexus\Approval\Domain\Entities\ApprovalDecision as ApprovalDecisionEntity;
use App\Domains\Nexus\Approval\Domain\Repositories\ApprovalDecisionRepositoryInterface;
use App\Domains\Nexus\Approval\Domain\ValueObjects\ApprovalDecisionOutcome;
use App\Domains\Nexus\Approval\Infrastructure\Models\ApprovalDecision as ApprovalDecisionModel;
use App\Domains\Nexus\Business\Domain\ValueObjects\TeamMemberRole;
use DateTimeImmutable;

class EloquentApprovalDecisionRepository implements ApprovalDecisionRepositoryInterface
{
    public function findByApprovalRequestId(int $approvalRequestId): array
    {
        return ApprovalDecisionModel::query()
            ->where('approval_request_id', $approvalRequestId)
            ->orderBy('decided_at')
            ->get()
            ->map(fn (ApprovalDecisionModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(ApprovalDecisionEntity $decision): ApprovalDecisionEntity
    {
        $model = new ApprovalDecisionModel();
        $model->approval_request_id = $decision->approvalRequestId();
        $model->level_index = $decision->levelIndex();
        $model->role_required = $decision->roleRequired()->value;
        $model->decided_by_owner_id = $decision->decidedByOwnerId();
        $model->decision = $decision->decision()->value;
        $model->decided_at = $decision->decidedAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(ApprovalDecisionModel $model): ApprovalDecisionEntity
    {
        return new ApprovalDecisionEntity(
            id: $model->id,
            approvalRequestId: $model->approval_request_id,
            levelIndex: $model->level_index,
            roleRequired: TeamMemberRole::from($model->role_required),
            decidedByOwnerId: $model->decided_by_owner_id,
            decision: ApprovalDecisionOutcome::from($model->decision),
            decidedAt: DateTimeImmutable::createFromInterface($model->decided_at),
        );
    }
}
