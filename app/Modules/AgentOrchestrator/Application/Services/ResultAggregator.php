<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Services\ResultAggregatorInterface;
use InvalidArgumentException;

/**
 * The one `ResultAggregatorInterface` implementation (Phase 6, Stage 5,
 * §7.30). `aggregate()` builds a real `ExecutionResult` via
 * `ExecutionResult::fromSteps()` — that Entity's own constructor is
 * private specifically so `status`/`summary` are always derived from the
 * given steps, never caller-supplied (see its own docblock, "entity
 * decides its own outcome from the facts it already holds"); merging
 * every result's own steps into one list and letting `fromSteps()`
 * recompute the combined outcome is the only way to build a genuinely
 * correct aggregate `ExecutionResult`, not a `new ExecutionResult(...)`
 * call assembling a `summary` by hand.
 */
final class ResultAggregator implements ResultAggregatorInterface
{
    public function aggregate(array $results): ExecutionResult
    {
        if ($results === []) {
            throw new InvalidArgumentException('Cannot aggregate an empty list of ExecutionResults.');
        }

        $allSteps = [];
        $totalDuration = 0.0;

        foreach ($results as $result) {
            $allSteps = [...$allSteps, ...$result->steps];
            $totalDuration += $result->executionTimeSeconds;
        }

        return ExecutionResult::fromSteps($results[0]->goal, $allSteps, $totalDuration);
    }

    public function resolveConflicts(array $conflictingResults): ExecutionResult
    {
        if ($conflictingResults === []) {
            throw new InvalidArgumentException('Cannot resolve conflicts among an empty list of ExecutionResults.');
        }

        $best = $conflictingResults[0];

        foreach ($conflictingResults as $result) {
            if ($result->successRate() > $best->successRate()) {
                $best = $result;
            }
        }

        return $best;
    }
}
