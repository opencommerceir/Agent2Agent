<?php

namespace App\Domains\Nexus\Llm\Infrastructure\Repositories;

use App\Domains\Nexus\Llm\Domain\Entities\LLMUsageLog as LLMUsageLogEntity;
use App\Domains\Nexus\Llm\Domain\Repositories\LLMUsageLogRepositoryInterface;
use App\Domains\Nexus\Llm\Infrastructure\Models\LLMUsageLog as LLMUsageLogModel;
use DateTimeImmutable;

class EloquentLLMUsageLogRepository implements LLMUsageLogRepositoryInterface
{
    public function save(LLMUsageLogEntity $log): LLMUsageLogEntity
    {
        $model = new LLMUsageLogModel();
        $model->business_id = $log->businessId();
        $model->agent_id = $log->agentId();
        $model->feature = $log->feature();
        $model->provider = $log->provider();
        $model->model = $log->model();
        $model->prompt_tokens = $log->promptTokens();
        $model->completion_tokens = $log->completionTokens();
        $model->total_tokens = $log->totalTokens();
        $model->real_cost_usd = $log->realCostUsd();
        $model->charged_cost_usd = $log->chargedCostUsd();
        $model->margin_usd = $log->marginUsd();
        $model->latency_ms = $log->latencyMs();
        $model->from_fallback = $log->fromFallback();
        $model->success = $log->success();
        $model->error_message = $log->errorMessage();
        $model->save();

        return $this->toEntity($model);
    }

    public function findByBusinessId(int $businessId): array
    {
        return LLMUsageLogModel::query()
            ->where('business_id', $businessId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (LLMUsageLogModel $model) => $this->toEntity($model))
            ->all();
    }

    private function toEntity(LLMUsageLogModel $model): LLMUsageLogEntity
    {
        return new LLMUsageLogEntity(
            id: $model->id,
            businessId: $model->business_id,
            agentId: $model->agent_id,
            feature: $model->feature,
            provider: $model->provider,
            model: $model->model,
            promptTokens: $model->prompt_tokens,
            completionTokens: $model->completion_tokens,
            totalTokens: $model->total_tokens,
            realCostUsd: $model->real_cost_usd,
            chargedCostUsd: $model->charged_cost_usd,
            marginUsd: $model->margin_usd,
            latencyMs: $model->latency_ms,
            fromFallback: $model->from_fallback,
            success: $model->success,
            errorMessage: $model->error_message,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
