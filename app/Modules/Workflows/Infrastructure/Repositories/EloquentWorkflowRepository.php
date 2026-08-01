<?php

namespace App\Modules\Workflows\Infrastructure\Repositories;

use App\Modules\Workflows\Domain\Entities\Workflow as WorkflowEntity;
use App\Modules\Workflows\Domain\Entities\WorkflowAction as WorkflowActionEntity;
use App\Modules\Workflows\Domain\Entities\WorkflowLog as WorkflowLogEntity;
use App\Modules\Workflows\Domain\Entities\WorkflowRule as WorkflowRuleEntity;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\Threshold;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;
use App\Modules\Workflows\Infrastructure\Models\Workflow as WorkflowModel;
use App\Modules\Workflows\Infrastructure\Models\WorkflowAction as WorkflowActionModel;
use App\Modules\Workflows\Infrastructure\Models\WorkflowLog as WorkflowLogModel;
use App\Modules\Workflows\Infrastructure\Models\WorkflowRule as WorkflowRuleModel;
use DateTimeImmutable;

/**
 * Never deletes-and-reinserts rules/actions, and only ever inserts them
 * once (when $isNew) — Workflow rules/actions are immutable (mirrors
 * EloquentOrderRepository's/EloquentInvoiceRepository's own docblocks).
 *
 * Every read method eager-loads `rules`/`actions` (Phase 4 Stage 8,
 * Performance Optimization, §7.20) — toEntity() always reads both.
 * findActiveByEventType() in particular is the real hot path here: it
 * runs on every InventoryWasCommitted/CartWasAbandoned dispatch
 * (InventoryLowListener/CartAbandonedListener), so its N+1 (2 extra
 * queries per matching Workflow, on top of the one that found them) was
 * paid on every relevant Domain Event, not just an occasional list-page
 * view.
 */
class EloquentWorkflowRepository implements WorkflowRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?WorkflowEntity
    {
        $model = WorkflowModel::query()->with(['rules', 'actions'])->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function list(int $tenantId, ?WorkflowStatus $status, ?EventType $eventType): array
    {
        $builder = WorkflowModel::query()->with(['rules', 'actions'])->where('tenant_id', $tenantId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        if ($eventType !== null) {
            $builder->where('event_type', $eventType->value);
        }

        return $builder->orderBy('id')
            ->get()
            ->map(fn (WorkflowModel $model) => $this->toEntity($model))
            ->all();
    }

    public function findActiveByEventType(EventType $eventType, int $tenantId): array
    {
        return WorkflowModel::query()
            ->with(['rules', 'actions'])
            ->where('tenant_id', $tenantId)
            ->where('event_type', $eventType->value)
            ->where('status', WorkflowStatus::Active->value)
            ->get()
            ->map(fn (WorkflowModel $model) => $this->toEntity($model))
            ->all();
    }

    public function save(WorkflowEntity $workflow): WorkflowEntity
    {
        $isNew = $workflow->id() === null;

        $model = $isNew
            ? new WorkflowModel()
            : WorkflowModel::query()->where('tenant_id', $workflow->tenantId())->findOrFail($workflow->id());

        $model->tenant_id = $workflow->tenantId();
        $model->name = $workflow->name();
        $model->description = $workflow->description();
        $model->event_type = $workflow->eventType()->value;
        $model->status = $workflow->status()->value;
        $model->save();

        if ($isNew) {
            foreach ($workflow->rules() as $rule) {
                $model->rules()->create([
                    'condition_type' => $rule->conditionType(),
                    'field' => $rule->field(),
                    'threshold_value' => $rule->threshold()->value(),
                ]);
            }

            foreach ($workflow->actions() as $action) {
                $model->actions()->create([
                    'action_type' => $action->actionType(),
                    'parameters' => $action->parameters(),
                ]);
            }
        }

        return $this->toEntity($model->fresh(['rules', 'actions']));
    }

    public function saveLog(WorkflowLogEntity $log): WorkflowLogEntity
    {
        $model = new WorkflowLogModel();
        $model->workflow_id = $log->workflowId();
        $model->tenant_id = $log->tenantId();
        $model->event_data = $log->eventData();
        $model->actions_executed = $log->actionsExecuted();
        $model->status = $log->status();
        $model->save();

        return $this->toLogEntity($model);
    }

    public function listLogs(int $tenantId, ?int $workflowId, int $limit): array
    {
        $builder = WorkflowLogModel::query()->where('tenant_id', $tenantId);

        if ($workflowId !== null) {
            $builder->where('workflow_id', $workflowId);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (WorkflowLogModel $model) => $this->toLogEntity($model))
            ->all();
    }

    private function toEntity(WorkflowModel $model): WorkflowEntity
    {
        $rules = $model->rules->map(fn (WorkflowRuleModel $ruleModel) => WorkflowRuleEntity::create(
            $ruleModel->condition_type,
            $ruleModel->field,
            new Threshold($ruleModel->threshold_value),
        ))->all();

        $actions = $model->actions->map(fn (WorkflowActionModel $actionModel) => WorkflowActionEntity::create(
            $actionModel->action_type,
            $actionModel->parameters ?? [],
        ))->all();

        return new WorkflowEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            description: $model->description,
            eventType: EventType::from($model->event_type),
            status: WorkflowStatus::from($model->status),
            rules: $rules,
            actions: $actions,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    private function toLogEntity(WorkflowLogModel $model): WorkflowLogEntity
    {
        return new WorkflowLogEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            workflowId: $model->workflow_id,
            eventData: $model->event_data ?? [],
            actionsExecuted: $model->actions_executed ?? [],
            status: $model->status,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
