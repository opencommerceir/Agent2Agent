<?php

namespace App\Modules\AgentOrchestrator\Application\Services;

use App\Core\Application\DTOs\AuthContext;
use App\Modules\AgentOrchestrator\Application\Actions\ExecuteGoalAction;
use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Entities\AgentMessage;
use App\Modules\AgentOrchestrator\Domain\Entities\DelegationRequest;
use App\Modules\AgentOrchestrator\Domain\Exceptions\DelegationTimeoutException;
use App\Modules\AgentOrchestrator\Domain\Repositories\AgentMessageRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Repositories\DelegationRequestRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\Services\AgentCommunicationInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\MessageType;
use Throwable;

/**
 * The one `AgentCommunicationInterface` implementation (Phase 6, Stage 5,
 * §7.30). `requestDelegation()` reuses the *existing*, unmodified
 * `ExecuteGoalAction` to actually run the delegated sub-goal (Actions
 * composing Actions, HANDOFF §3 pattern #3) — with the *same* real
 * `AuthContext` the caller was itself invoked with, never a fabricated
 * "system agent" identity. There is no separate, permission-bearing
 * identity per persona in this codebase (`AgentType` is a per-call
 * planning classification, not an Agent identity — see
 * `docs/multi-agent-collaboration.md`'s own "Personas are not identities"
 * section) — delegating to a different persona changes *which
 * `AgentProfile` plans the sub-goal*, never *what the real, already-
 * authenticated caller is actually allowed to do*.
 *
 * Saves the `DelegationRequest` exactly once, already in its final
 * terminal state (`Completed`/`Failed`/`Timeout`) — `markInProgress()` is
 * an in-memory-only transition, satisfying the Entity's own state-machine
 * guard before a terminal mutator may run, not a separately persisted
 * row. A real future async flow (a queued delegation another process
 * later picks up) would need an actual `Pending` row persisted up front;
 * this stage's own delegation always runs synchronously, start to finish,
 * within one call, so that intermediate row has no real observer yet.
 */
final class AgentCommunicationService implements AgentCommunicationInterface
{
    public function __construct(
        private readonly AgentMessageRepositoryInterface $messages,
        private readonly DelegationRequestRepositoryInterface $delegations,
        private readonly ExecuteGoalAction $executeGoal,
    ) {
    }

    public function send(AgentMessage $message): void
    {
        $message->markAsSent();
        $this->messages->save($message);
    }

    public function receive(int $tenantId, AgentType $agentType, int $limit): array
    {
        return $this->messages->findForAgent($tenantId, $agentType, $limit);
    }

    public function requestDelegation(DelegationRequest $request, AuthContext $context): ExecutionResultData
    {
        $this->send(AgentMessage::create(
            tenantId: $request->tenantId,
            fromAgentType: $request->fromAgentType,
            toAgentType: $request->toAgentType,
            messageType: MessageType::Delegation,
            content: ['task' => $request->task, 'priority' => $request->priority->value()],
            parentExecutionId: $request->parentExecutionId,
        ));

        $request->markInProgress();
        $start = microtime(true);

        try {
            $result = $this->executeGoal->execute($request->task, $request->toAgentType, $context);
        } catch (Throwable $e) {
            $request->markFailed($e->getMessage());
            $this->delegations->save($request);
            $this->recordResponse($request, ['error' => $e->getMessage()]);

            throw $e;
        }

        $elapsed = microtime(true) - $start;

        if ($elapsed > $request->timeoutSeconds) {
            $request->markTimeout($elapsed);
            $this->delegations->save($request);
            $this->recordResponse($request, ['error' => "Timed out after {$elapsed}s"]);

            throw new DelegationTimeoutException(
                "Delegation to [{$request->toAgentType->value}] exceeded its own {$request->timeoutSeconds}s timeout ({$elapsed}s elapsed)."
            );
        }

        $request->markCompleted($result->toArray());
        $this->delegations->save($request);
        $this->recordResponse($request, ['status' => $result->status, 'summary' => $result->summary]);

        return $result;
    }

    /**
     * @param array<string, mixed> $content
     */
    private function recordResponse(DelegationRequest $request, array $content): void
    {
        $message = AgentMessage::create(
            tenantId: $request->tenantId,
            fromAgentType: $request->toAgentType,
            toAgentType: $request->fromAgentType,
            messageType: MessageType::Response,
            content: $content,
            parentExecutionId: $request->parentExecutionId,
        );
        $message->markAsSent();
        $message->markAsProcessed();
        $this->messages->save($message);
    }
}
