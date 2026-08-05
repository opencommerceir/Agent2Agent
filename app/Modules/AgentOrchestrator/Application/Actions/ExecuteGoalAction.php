<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Events\GoalCompleted;
use App\Modules\AgentOrchestrator\Domain\Events\GoalReceived;
use App\Modules\AgentOrchestrator\Domain\Exceptions\GoalExecutionFailedException;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentProfileRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\LearningServiceInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlanExecutorInterface;
use App\Modules\AgentOrchestrator\Domain\Services\PlannerInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single entry point every Agent-facing surface (the HTTP
 * `/api/agents/{agent_type}` Controller and this module's own
 * `agent.goal.execute` MCP capability alike) calls into — goal -> plan ->
 * execute -> persist -> result, matching HANDOFF §3 pattern #19 ("every
 * transport reuses the same Action, never re-implements the flow for a
 * second surface").
 *
 * Takes `AuthContext` directly — the one Action in this codebase that
 * does, and only because it must forward a complete, valid AuthContext
 * through PlanExecutorInterface into arbitrary downstream capability
 * calls (see `ToolInvokerInterface`'s own docblock for the full
 * reasoning). `GetExecutionResultAction`/`ListExecutionsAction`, which
 * never invoke another capability, still take plain `int $tenantId` per
 * HANDOFF §3 pattern #1 — this exception is deliberately as narrow as
 * possible.
 *
 * Loads the calling `AgentType`'s own `AgentProfile` (§7.27) before
 * planning — `AgentProfileNotFoundException` (a real 404, e.g. an
 * `AgentType` case with no `config/agents/{type}.php` of its own yet) is
 * allowed to propagate unwrapped, same as any other Action's own
 * `*NotFoundException`; only a genuine *planning* failure (the Planner
 * itself throwing) gets wrapped in `GoalExecutionFailedException` below.
 *
 * **Consults `LearningServiceInterface` before either Planner (Phase 6,
 * Stage 4, §7.29).** `PlannerInterface` is deliberately tenant-independent
 * (see its own docblock) — a *learned* suggestion is not, so it can't live
 * behind that Interface without widening a contract two other, already-
 * reviewed implementations (`DeterministicPlanner`/`LLMPlanner`) share.
 * This Action already holds a full `AuthContext` (the one other deliberate
 * exception in this codebase to "no AuthContext below the MCP boundary,"
 * see class docblock above) — asking "has *this* tenant already solved a
 * goal like this" here, before either Planner is even consulted, keeps
 * both Planners completely unaware learning exists, and applies it
 * uniformly regardless of which one `PlannerInterface` is currently bound
 * to (`config('agent-orchestrator.planner.type')`).
 */
final class ExecuteGoalAction
{
    public function __construct(
        private readonly AgentProfileRepositoryInterface $profiles,
        private readonly PlannerInterface $planner,
        private readonly PlanExecutorInterface $executor,
        private readonly ExecutionMemoryRepositoryInterface $memory,
        private readonly LearningServiceInterface $learning,
    ) {
    }

    public function execute(string $goalText, AgentType $agentType, AuthContext $context): ExecutionResultData
    {
        $goal = Goal::fromText($goalText, $agentType);

        Log::info('Goal received', [
            'goal' => $goal->text,
            'agent_type' => $agentType->value,
            'tenant_id' => $context->tenantId,
            'agent_id' => $context->agentId,
        ]);
        Event::dispatch(new GoalReceived($goal, $context->tenantId, $context->agentId));

        $plan = $this->learning->suggestPlan($goal, $context->tenantId);

        if ($plan !== null) {
            Log::info('Using learned plan from history', ['goal' => $goal->text, 'tenant_id' => $context->tenantId]);
        } else {
            $profile = $this->profiles->findByType($agentType->value);

            try {
                $plan = $this->planner->createPlan($goal, $profile);
            } catch (Throwable $e) {
                Log::error('Plan creation failed', ['goal' => $goal->text, 'error' => $e->getMessage()]);

                throw new GoalExecutionFailedException(
                    "Failed to create an execution plan for goal [{$goal->text}]: {$e->getMessage()}",
                    previous: $e,
                );
            }
        }

        $result = $this->executor->execute($plan, $context);

        $saved = $this->memory->save($result, $context->tenantId, $context->agentId, $agentType);

        Event::dispatch(new GoalCompleted($result, $context->tenantId, $context->agentId));
        Log::info('Goal execution finished', [
            'goal' => $goal->text,
            'status' => $result->status,
            'execution_time' => $result->executionTimeSeconds,
        ]);

        return ExecutionResultData::fromEntity($result, $saved['id']);
    }
}
