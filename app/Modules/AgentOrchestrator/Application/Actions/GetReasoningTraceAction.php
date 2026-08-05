<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\ReasoningTraceData;
use App\Modules\AgentOrchestrator\Domain\Repositories\ReasoningTraceRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;

/**
 * Backs `agent.reasoning.trace` (Phase 6, Stage 6, §7.31). Plain
 * `int $tenantId`, never `AuthContext` — this Action never invokes another
 * capability, the same HANDOFF §3 pattern #1 shape `GetExecutionInsightsAction`
 * (§7.29) already establishes.
 */
final class GetReasoningTraceAction
{
    public function __construct(
        private readonly ReasoningTraceRepositoryInterface $reasoningTraces,
    ) {
    }

    /**
     * @return array{pre_execution: ?ReasoningTraceData, post_execution: ?ReasoningTraceData}
     */
    public function execute(int $tenantId, int $executionId): array
    {
        $traces = $this->reasoningTraces->findByExecution($tenantId, $executionId);

        $pre = null;
        $post = null;

        foreach ($traces as $trace) {
            if ($trace->reasoningType === ReasoningType::PreExecution) {
                $pre = ReasoningTraceData::fromEntity($trace);
            } else {
                $post = ReasoningTraceData::fromEntity($trace);
            }
        }

        return ['pre_execution' => $pre, 'post_execution' => $post];
    }
}
