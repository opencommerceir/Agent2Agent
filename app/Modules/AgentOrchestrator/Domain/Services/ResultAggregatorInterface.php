<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;

/**
 * Combines several already-finished `ExecutionResult`s into one (Phase 6,
 * Stage 5, §7.30) — pure Domain calculation, no Repository dependency, the
 * same "only combines what it's given" shape `WorkflowEvaluator`/
 * `PricingService` already establish. Built and tested this stage with no
 * automatic caller yet — today's `agent.collaboration.delegate` capability
 * only ever delegates to *one* target persona per call, so `PlanExecutor`'s
 * own existing per-step result handling already covers it (one delegation
 * = one step's own nested output, no aggregation needed). The natural
 * future caller is a delegation to *multiple* personas at once — see
 * `docs/multi-agent-collaboration.md`'s own "Known scope decisions," the
 * same "built the mechanism, no caller yet" shape `ExecutionPlanData`
 * carried between §7.26 and §7.29.
 */
interface ResultAggregatorInterface
{
    /**
     * @param list<ExecutionResult> $results non-empty
     */
    public function aggregate(array $results): ExecutionResult;

    /**
     * Picks the result with the highest `successRate()` — ties keep
     * whichever came first in the given list.
     *
     * @param list<ExecutionResult> $conflictingResults non-empty
     */
    public function resolveConflicts(array $conflictingResults): ExecutionResult;
}
