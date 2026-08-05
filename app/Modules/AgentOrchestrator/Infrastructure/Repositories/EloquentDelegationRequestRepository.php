<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest as DelegationRequestEntity;
use App\Modules\AgentOrchestrator\Domain\Repositories\DelegationRequestRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationPriority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationStatus;
use App\Modules\AgentOrchestrator\Infrastructure\Models\DelegationRequest as DelegationRequestModel;

class EloquentDelegationRequestRepository implements DelegationRequestRepositoryInterface
{
    public function save(DelegationRequestEntity $request): void
    {
        if ($request->id() === null) {
            $model = new DelegationRequestModel();
            $this->fill($model, $request);
            $model->save();

            $request->assignId($model->id);

            return;
        }

        $model = DelegationRequestModel::query()->findOrFail($request->id());
        $this->fill($model, $request);
        $model->save();
    }

    public function findById(int $id, int $tenantId): ?DelegationRequestEntity
    {
        $model = DelegationRequestModel::query()->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    private function fill(DelegationRequestModel $model, DelegationRequestEntity $request): void
    {
        $model->tenant_id = $request->tenantId;
        $model->parent_execution_id = $request->parentExecutionId;
        $model->from_agent_type = $request->fromAgentType->value;
        $model->to_agent_type = $request->toAgentType->value;
        $model->task = $request->task;
        $model->priority = $request->priority->value();
        $model->timeout_seconds = $request->timeoutSeconds;
        $model->status = $request->status()->value;
        $model->result = $request->result();
        $model->completed_at = $request->completedAt();
    }

    private function toEntity(DelegationRequestModel $model): DelegationRequestEntity
    {
        return DelegationRequestEntity::reconstruct(
            id: $model->id,
            tenantId: $model->tenant_id,
            parentExecutionId: $model->parent_execution_id,
            fromAgentType: AgentType::from($model->from_agent_type),
            toAgentType: AgentType::from($model->to_agent_type),
            task: $model->task,
            priority: new DelegationPriority($model->priority),
            timeoutSeconds: $model->timeout_seconds,
            status: DelegationStatus::from($model->status),
            result: $model->result,
            createdAt: $model->created_at->toDateTimeImmutable(),
            completedAt: $model->completed_at?->toDateTimeImmutable(),
        );
    }
}
