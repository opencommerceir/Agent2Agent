<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Entities\Goal;
use App\Modules\AgentOrchestrator\Domain\Events\GoalCompleted;
use App\Modules\AgentOrchestrator\Domain\Events\GoalReceived;
use App\Modules\AgentOrchestrator\Domain\Exceptions\GoalExecutionFailedException;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
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
 */
final class ExecuteGoalAction
{
    public function __construct(
        private readonly PlannerInterface $planner,
        private readonly PlanExecutorInterface $executor,
        private readonly ExecutionMemoryRepositoryInterface $memory,
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

        try {
            $plan = $this->planner->createPlan($goal);
        } catch (Throwable $e) {
            Log::error('Plan creation failed', ['goal' => $goal->text, 'error' => $e->getMessage()]);

            throw new GoalExecutionFailedException(
                "Failed to create an execution plan for goal [{$goal->text}]: {$e->getMessage()}",
                previous: $e,
            );
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
