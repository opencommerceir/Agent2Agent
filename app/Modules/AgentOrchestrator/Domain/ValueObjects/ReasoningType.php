<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * Which half of a `ReasoningTrace` this is (Phase 6, Stage 6, §7.31) —
 * `PreExecution` ("think before act," produced before a Plan is created)
 * and `PostExecution` ("reflect after acting," produced once a real
 * `ExecutionResult` exists). One `ExecuteGoalAction` call produces exactly
 * one of each, never more — see `ReasoningTrace`'s own docblock.
 */
enum ReasoningType: string
{
    case PreExecution = 'pre_execution';
    case PostExecution = 'post_execution';
}
