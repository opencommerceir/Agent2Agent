<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionStep as ExecutionStepEntity;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\StepStatus;
use App\Modules\AgentOrchestrator\Infrastructure\Models\Execution as ExecutionModel;
use App\Modules\AgentOrchestrator\Infrastructure\Models\ExecutionStep as ExecutionStepModel;

/**
 * `save()` always inserts a brand-new row — an Execution is a frozen
 * historical record of one already-finished goal run (the same
 * write-once shape `Order`/`Invoice` already establish), never updated
 * afterward. Every read method eager-loads `steps` (the same "always
 * eager-load the owned child collection" discipline the Tech Debt
 * Sprint's own N+1 fixes established, HANDOFF §7.20/§8.22) — a list
 * endpoint reading N Executions must never cost 1+N queries.
 */
class EloquentExecutionMemoryRepository implements ExecutionMemoryRepositoryInterface
{
    public function save(ExecutionResult $result, int $tenantId, int $agentId, AgentType $agentType): array
    {
        $model = new ExecutionModel();
        $model->tenant_id = $tenantId;
        $model->agent_id = $agentId;
        $model->agent_type = $agentType->value;
        $model->goal_text = $result->goal->text;
        $model->status = $result->status;
        $model->summary = $result->summary;
        $model->execution_time_ms = (int) round($result->executionTimeSeconds * 1000);
        $model->save();

        foreach ($result->steps as $sequence => $step) {
            $model->steps()->create([
                'sequence' => $sequence,
                'capability' => $step->capability,
                'input' => $step->input,
                'priority' => $step->priority->value,
                'status' => $step->status()->value,
                'output' => $step->output(),
                'error_message' => $step->errorMessage(),
            ]);
        }

        return ['id' => $model->id, 'result' => $result];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $model = ExecutionModel::query()->with('steps')->where('tenant_id', $tenantId)->find($id);

        return $model ? $this->toRecord($model) : null;
    }

    public function list(int $tenantId, ?AgentType $agentType, ?string $status, int $limit): array
    {
        $builder = ExecutionModel::query()->with('steps')->where('tenant_id', $tenantId);

        if ($agentType !== null) {
            $builder->where('agent_type', $agentType->value);
        }

        if ($status !== null) {
            $builder->where('status', $status);
        }

        return $builder->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (ExecutionModel $model) => $this->toRecord($model))
            ->all();
    }

    /**
     * @return array{id: int, tenantId: int, agentId: int, agentType: AgentType, result: ExecutionResult}
     */
    private function toRecord(ExecutionModel $model): array
    {
        return [
            'id' => $model->id,
            'tenantId' => $model->tenant_id,
            'agentId' => $model->agent_id,
            'agentType' => AgentType::from($model->agent_type),
            'result' => $this->toResult($model),
        ];
    }

    private function toResult(ExecutionModel $model): ExecutionResult
    {
        $goal = Goal::fromText($model->goal_text, AgentType::from($model->agent_type));

        $steps = $model->steps->map(fn (ExecutionStepModel $stepModel) => ExecutionStepEntity::reconstruct(
            capability: $stepModel->capability,
            input: $stepModel->input ?? [],
            priority: Priority::from($stepModel->priority),
            status: StepStatus::from($stepModel->status),
            output: $stepModel->output,
            errorMessage: $stepModel->error_message,
        ))->all();

        return ExecutionResult::fromSteps($goal, $steps, $model->execution_time_ms / 1000);
    }
}
