<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Domain\Services\LearningServiceInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Backs `agent.memory.insights` (Phase 6, Stage 4, §7.29). Plain
 * `int $tenantId`/`AgentType`, never `AuthContext` — this Action never
 * invokes another capability, the same HANDOFF §3 pattern #1 shape
 * `GetExecutionResultAction`/`ListExecutionsAction` already establish.
 */
final class GetExecutionInsightsAction
{
    public function __construct(
        private readonly LearningServiceInterface $learning,
    ) {
    }

    /**
     * @return array{total_executions: int, average_duration: float, most_used_capabilities: list<array{capability: string, count: int}>, success_rate: float, recent_goals: list<string>}
     */
    public function execute(int $tenantId, AgentType $agentType): array
    {
        return $this->learning->getInsights($tenantId, $agentType);
    }
}
