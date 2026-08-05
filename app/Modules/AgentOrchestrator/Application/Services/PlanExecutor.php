<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionPlan;
use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;
use App\Modules\AgentOrchestrator\Domain\Events\StepExecuted;
use App\Modules\AgentOrchestrator\Domain\Services\PlanExecutorInterface;
use App\Modules\AgentOrchestrator\Domain\Services\ToolInvokerInterface;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Runs an ExecutionPlan's steps strictly in order, one at a time — no
 * concurrency, no reordering by Priority (see that Value Object's own
 * docblock). Every step's own failure is caught here and recorded on the
 * step itself (`markAsFailed()`); it is never rethrown and never aborts
 * the remaining steps — this module's own explicit rule, the same
 * "an ordinary row failure is just a recorded outcome" shape
 * `ProcessBulkImportJob`'s own per-row try/catch already establishes
 * (HANDOFF §7.23), applied here per *step* instead of per *row*.
 *
 * Dispatches `StepExecuted` at each of a step's own transitions
 * ('started' before invoking, 'completed'/'failed' after) — actual
 * logging lives in `LogExecutionStepListener`, not here, so this class's
 * only job stays "run the plan," not "run the plan and also own how it's
 * logged."
 */
final class PlanExecutor implements PlanExecutorInterface
{
    public function __construct(
        private readonly ToolInvokerInterface $toolInvoker,
    ) {
    }

    public function execute(ExecutionPlan $plan, AuthContext $context): ExecutionResult
    {
        $planStartedAt = microtime(true);
        $steps = [];

        foreach ($plan->steps as $step) {
            $step->markAsRunning();
            Event::dispatch(new StepExecuted($step, 'started'));

            $stepStartedAt = microtime(true);

            try {
                $output = $this->toolInvoker->invoke($step->capability, $step->input, $context);
                $step->markAsCompleted($output);

                Event::dispatch(new StepExecuted($step, 'completed', $this->elapsedMs($stepStartedAt)));
            } catch (Throwable $e) {
                $step->markAsFailed($e->getMessage());

                Event::dispatch(new StepExecuted($step, 'failed', $this->elapsedMs($stepStartedAt)));
                // Deliberately not rethrown — a single tool failure must
                // never abort the rest of the plan.
            }

            $steps[] = $step;
        }

        return ExecutionResult::fromSteps($plan->goal, $steps, microtime(true) - $planStartedAt);
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
