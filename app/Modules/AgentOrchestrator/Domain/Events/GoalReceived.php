<?php

namespace App\Modules\AgentOrchestrator\Domain\Events;

use App\Modules\AgentOrchestrator\Domain\Entities\Goal;

/**
 * Domain event: dispatched by ExecuteGoalAction the moment a Goal has
 * been parsed and is about to be handed to the Planner — before any
 * capability has been invoked. Carries explicit identifiers, never a
 * richer object, the same reasoning every other cross-cutting event in
 * this codebase gives (Commerce's `InventoryWasCommitted`, HANDOFF §7.9).
 */
final class GoalReceived
{
    public function __construct(
        public readonly Goal $goal,
        public readonly int $tenantId,
        public readonly int $agentId,
    ) {
    }
}
