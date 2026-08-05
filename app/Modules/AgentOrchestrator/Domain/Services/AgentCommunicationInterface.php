<?php

namespace App\Modules\AgentOrchestrator\Domain\Services;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage;
use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

/**
 * Agent-to-agent communication and delegation (Phase 6, Stage 5, §7.30).
 *
 * `requestDelegation()` takes `AuthContext` directly and returns the
 * Application-layer `ExecutionResultData`, not a plain Domain type — a
 * third, deliberate exception to this codebase's usual "Domain Service
 * interfaces take plain scalars, never `AuthContext`/Application DTOs"
 * rule (HANDOFF §3 pattern #1), alongside `PlanExecutorInterface`/
 * `ToolInvokerInterface` (§7.26). The reasoning is identical: this
 * method's whole job is re-entering the same Goal-execution boundary
 * those two interfaces already re-enter (it calls `ExecuteGoalAction`
 * internally, Actions composing Actions, §3 pattern #3) — there is no
 * real Domain-only shape to hand back that every actual caller doesn't
 * immediately need re-serialized anyway (the MCP capability this backs,
 * `agent.collaboration.delegate`, returns exactly this DTO's own
 * `toArray()`). Reconstructing a separate Domain `ExecutionResult` purely
 * to satisfy layer purity, when nothing downstream needs it as one, would
 * be ceremony with no real benefit.
 */
interface AgentCommunicationInterface
{
    public function send(AgentMessage $message): void;

    /**
     * @return list<AgentMessage>
     */
    public function receive(int $tenantId, AgentType $agentType, int $limit): array;

    public function requestDelegation(DelegationRequest $request, AuthContext $context): ExecutionResultData;
}
