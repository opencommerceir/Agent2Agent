<?php

namespace App\Modules\AgentOrchestrator\Domain\Events;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;

/**
 * Domain event: dispatched by ExecuteGoalAction once PlanExecutor returns
 * a final ExecutionResult (whatever its status — 'completed', 'partial',
 * 'failed', or 'empty') and it has been persisted. Nothing in this stage
 * listens for it yet — same "event exists before its own Listener does"
 * shape `EventType::CartAbandoned` had before the Tech Debt Sprint wired
 * it (HANDOFF §7.9/§7.13); a future Notifications hook ("your CEO Agent's
 * goal just finished") is the natural first Listener.
 */
final class GoalCompleted
{
    public function __construct(
        public readonly ExecutionResult $result,
        public readonly int $tenantId,
        public readonly int $agentId,
    ) {
    }
}
