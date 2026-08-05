<?php

namespace App\Modules\AgentOrchestrator\Infrastructure\Repositories;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPattern as ExecutionPatternEntity;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionPatternRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Infrastructure\Models\ExecutionPattern as ExecutionPatternModel;

/**
 * `save()` upserts by the Entity's own `id()` — null means "not yet
 * persisted," insert a new row and `assignId()` the real one back onto the
 * given Entity (the same "id becomes known only after persistence" shape
 * `EloquentExecutionMemoryRepository::save()` already has, just mutating
 * the Entity in place here instead of returning an array); a non-null id
 * means "update this pattern's own row," never a second insert for the
 * same (tenantId, goalPattern, agentType) — see `findExisting()`, always
 * consulted by the caller (`LearnFromExecutionListener`) before deciding
 * which branch applies.
 */
class EloquentExecutionPatternRepository implements ExecutionPatternRepositoryInterface
{
    public function save(ExecutionPatternEntity $pattern): void
    {
        if ($pattern->id() === null) {
            $model = new ExecutionPatternModel();
            $this->fill($model, $pattern);
            $model->save();

            $pattern->assignId($model->id);

            return;
        }

        $model = ExecutionPatternModel::query()->findOrFail($pattern->id());
        $this->fill($model, $pattern);
        $model->save();
    }

    public function findExisting(int $tenantId, string $goalPattern, AgentType $agentType): ?ExecutionPatternEntity
    {
        $model = ExecutionPatternModel::query()
            ->where('tenant_id', $tenantId)
            ->where('goal_pattern', $goalPattern)
            ->where('agent_type', $agentType->value)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findSimilarPatterns(int $tenantId, string $goal, AgentType $agentType, int $limit): array
    {
        $candidates = ExecutionPatternModel::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_type', $agentType->value)
            ->orderByDesc('success_rate')
            ->orderByDesc('usage_count')
            ->get()
            ->map(fn (ExecutionPatternModel $model) => $this->toEntity($model));

        return $candidates
            ->filter(fn (ExecutionPatternEntity $pattern) => $pattern->matches($goal))
            ->take($limit)
            ->values()
            ->all();
    }

    private function fill(ExecutionPatternModel $model, ExecutionPatternEntity $pattern): void
    {
        $model->tenant_id = $pattern->tenantId;
        $model->goal_pattern = $pattern->goalPattern;
        $model->agent_type = $pattern->agentType->value;
        $model->successful_capabilities = $pattern->successfulCapabilities();
        $model->failed_capabilities = $pattern->failedCapabilities();
        $model->usage_count = $pattern->usageCount();
        $model->success_rate = $pattern->successRate();
        $model->last_used_at = $pattern->lastUsedAt();
    }

    private function toEntity(ExecutionPatternModel $model): ExecutionPatternEntity
    {
        return ExecutionPatternEntity::reconstruct(
            id: $model->id,
            tenantId: $model->tenant_id,
            goalPattern: $model->goal_pattern,
            agentType: AgentType::from($model->agent_type),
            successfulCapabilities: $model->successful_capabilities ?? [],
            failedCapabilities: $model->failed_capabilities ?? [],
            usageCount: $model->usage_count,
            successRate: $model->success_rate,
            lastUsedAt: $model->last_used_at->toDateTimeImmutable(),
        );
    }
}
