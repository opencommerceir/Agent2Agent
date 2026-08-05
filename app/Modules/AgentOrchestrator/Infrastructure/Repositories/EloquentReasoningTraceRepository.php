<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace as ReasoningTraceEntity;
use App\Modules\AgentOrchestrator\Domain\Repositories\ReasoningTraceRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use App\Modules\AgentOrchestrator\Infrastructure\Models\ReasoningTrace as ReasoningTraceModel;
use LogicException;

/**
 * Insert-only — a `ReasoningTrace` is never edited after it's written, the
 * same append-only shape `EloquentAgentMessageRepository` already
 * establishes (§7.30), one step simpler: no update branch at all, since
 * nothing in this stage ever re-saves an already-persisted trace.
 */
class EloquentReasoningTraceRepository implements ReasoningTraceRepositoryInterface
{
    public function save(ReasoningTraceEntity $trace): void
    {
        if ($trace->executionId() === null) {
            throw new LogicException(
                'Cannot save a ReasoningTrace with no executionId assigned yet — call assignExecutionId() first (see ReasoningTrace\'s own docblock).'
            );
        }

        $model = new ReasoningTraceModel();
        $model->tenant_id = $trace->tenantId;
        $model->execution_id = $trace->executionId();
        $model->agent_type = $trace->agentType->value;
        $model->goal_text = $trace->goalText;
        $model->reasoning_type = $trace->reasoningType->value;
        $model->thoughts = $trace->thoughts;
        $model->alternatives = array_map(fn (AlternativePlan $alternative) => $alternative->toArray(), $trace->alternatives);
        $model->confidence_score = $trace->confidenceScore->value;
        $model->decision = $trace->decision;
        $model->explanation = $trace->explanation;
        $model->created_at = $trace->createdAt;
        $model->save();

        $trace->assignId($model->id);
    }

    public function findByExecution(int $tenantId, int $executionId): array
    {
        $models = ReasoningTraceModel::query()
            ->where('tenant_id', $tenantId)
            ->where('execution_id', $executionId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $models->map(fn (ReasoningTraceModel $model) => $this->toEntity($model))->all();
    }

    private function toEntity(ReasoningTraceModel $model): ReasoningTraceEntity
    {
        $alternatives = array_map(
            fn (array $alternative) => AlternativePlan::create($alternative['plan'], (float) $alternative['confidence'], $alternative['reason']),
            $model->alternatives ?? [],
        );

        return ReasoningTraceEntity::reconstruct(
            id: $model->id,
            tenantId: $model->tenant_id,
            agentType: AgentType::from($model->agent_type),
            goalText: $model->goal_text,
            reasoningType: ReasoningType::from($model->reasoning_type),
            thoughts: $model->thoughts ?? [],
            alternatives: $alternatives,
            confidenceScore: ConfidenceScore::fromFloat((float) $model->confidence_score),
            decision: $model->decision,
            explanation: $model->explanation,
            executionId: $model->execution_id,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}
