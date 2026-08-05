<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest;
use App\Modules\AgentOrchestrator\Domain\Services\AgentCommunicationInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationPriority;

/**
 * Backs `agent.collaboration.delegate` (Phase 6, Stage 5, §7.30) — an
 * ordinary MCP capability, reachable from any plan step exactly like any
 * other capability (`PlanExecutor` -> `CapabilityToolInvoker` doesn't
 * distinguish this from `commerce.coupon.create`). Takes `AuthContext`
 * directly for the same reason `AgentCommunicationInterface::requestDelegation()`
 * does (see that Interface's own docblock) — the one other deliberate
 * exception in this Action family, alongside `ExecuteGoalAction` itself.
 *
 * `priority`/`timeoutSeconds` are not part of this capability's own
 * request schema beyond an optional `priority` — a caller-supplied
 * timeout was never requested; `DEFAULT_TIMEOUT_SECONDS` matches this
 * stage's own worked example.
 */
final class DelegateToAgentAction
{
    private const DEFAULT_PRIORITY = 5;

    private const DEFAULT_TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly AgentCommunicationInterface $communication,
    ) {
    }

    /**
     * @return array{delegation_id: ?int, result: ExecutionResultData}
     */
    public function execute(
        AgentType $fromAgentType,
        AgentType $toAgentType,
        string $task,
        ?int $priority,
        int $tenantId,
        AuthContext $context,
    ): array {
        $request = DelegationRequest::create(
            tenantId: $tenantId,
            fromAgentType: $fromAgentType,
            toAgentType: $toAgentType,
            task: $task,
            priority: new DelegationPriority($priority ?? self::DEFAULT_PRIORITY),
            timeoutSeconds: self::DEFAULT_TIMEOUT_SECONDS,
        );

        $result = $this->communication->requestDelegation($request, $context);

        return ['delegation_id' => $request->id(), 'result' => $result];
    }
}
