<?php

namespace App\Modules\AgentOrchestrator\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown by `AgentCommunicationService::requestDelegation()` when the
 * delegated `ExecuteGoalAction` call takes longer than the
 * `DelegationRequest`'s own `timeoutSeconds` (Phase 6, Stage 5, §7.30) —
 * implements neither Core marker interface, the same reasoning
 * `LLMRequestFailedException`/`WooCommerceApiException` already give
 * (an operational timeout is neither "not found" nor "a business-rule
 * conflict"), so `MCPExceptionHandler`'s default branch maps it to
 * `INTERNAL_ERROR`/500. Never expected to abort a *parent* plan's own
 * execution — thrown from inside a `agent.collaboration.delegate`
 * capability call, `PlanExecutor` catches it the same way it catches any
 * other step failure and continues to the next step (HANDOFF gotcha:
 * "one failed step must never abort the rest of the plan," unchanged
 * this stage).
 */
final class DelegationTimeoutException extends RuntimeException
{
}
