<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationPriority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\DelegationStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * One persona-to-persona task hand-off's own work-tracking row (Phase 6,
 * Stage 5, §7.30) — the state-machine sibling to `AgentMessage`'s own
 * append-only communication log; a `DelegationRequest` is mutated in place
 * through its lifecycle (`Pending` -> `InProgress` -> exactly one of
 * `Completed`/`Failed`/`Timeout`), the same "state machine, not an
 * immutable record" shape `Shipment`/`WarehouseTransfer` already
 * establish, unlike `AgentMessage`'s own append-only log entries.
 *
 * `$result` is a plain `?array` (the same shape `ExecutionResultData::toArray()`
 * produces), never the real Domain `ExecutionResult` — storing the rich
 * Domain Entity here would make this Domain Entity depend on another
 * Entity's full object graph for a field that only ever needs to be
 * serialized to/from the `result` JSON column; a plain array avoids that
 * without losing anything the DB row itself needs to carry. On failure,
 * `$result` instead holds `{"error": "..."}` — one JSON column serving
 * both outcomes, matching the request's own schema (no separate
 * `error_message` column was requested).
 *
 * `$parentExecutionId` is nullable and, this stage, always null in
 * practice — a delegation happens *while* a plan is still running, before
 * `ExecuteGoalAction` has persisted the parent's own `Execution` row (that
 * happens only after the whole plan finishes) — see
 * `docs/multi-agent-collaboration.md`'s own "Known scope decisions."
 */
final class DelegationRequest
{
    private ?int $id;

    private DelegationStatus $status;

    /**
     * @var ?array<string, mixed>
     */
    private ?array $result = null;

    private ?DateTimeImmutable $completedAt = null;

    private function __construct(
        ?int $id,
        public readonly int $tenantId,
        public readonly ?int $parentExecutionId,
        public readonly AgentType $fromAgentType,
        public readonly AgentType $toAgentType,
        public readonly string $task,
        public readonly DelegationPriority $priority,
        public readonly int $timeoutSeconds,
        DelegationStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->status = $status;
    }

    public static function create(
        int $tenantId,
        AgentType $fromAgentType,
        AgentType $toAgentType,
        string $task,
        DelegationPriority $priority,
        int $timeoutSeconds,
        ?int $parentExecutionId = null,
    ): self {
        if ($fromAgentType === $toAgentType) {
            throw new InvalidArgumentException(
                "Cannot delegate from [{$fromAgentType->value}] to itself — delegation must target a different persona."
            );
        }

        $trimmedTask = trim($task);

        if ($trimmedTask === '') {
            throw new InvalidArgumentException('Delegation task cannot be empty.');
        }

        return new self(
            id: null,
            tenantId: $tenantId,
            parentExecutionId: $parentExecutionId,
            fromAgentType: $fromAgentType,
            toAgentType: $toAgentType,
            task: $trimmedTask,
            priority: $priority,
            timeoutSeconds: $timeoutSeconds,
            status: DelegationStatus::Pending,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param ?array<string, mixed> $result
     */
    public static function reconstruct(
        int $id,
        int $tenantId,
        ?int $parentExecutionId,
        AgentType $fromAgentType,
        AgentType $toAgentType,
        string $task,
        DelegationPriority $priority,
        int $timeoutSeconds,
        DelegationStatus $status,
        ?array $result,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $completedAt,
    ): self {
        $request = new self(
            id: $id,
            tenantId: $tenantId,
            parentExecutionId: $parentExecutionId,
            fromAgentType: $fromAgentType,
            toAgentType: $toAgentType,
            task: $task,
            priority: $priority,
            timeoutSeconds: $timeoutSeconds,
            status: $status,
            createdAt: $createdAt,
        );
        $request->result = $result;
        $request->completedAt = $completedAt;

        return $request;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("DelegationRequest already has id [{$this->id}]; assignId() is one-time only.");
        }

        $this->id = $id;
    }

    public function status(): DelegationStatus
    {
        return $this->status;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function result(): ?array
    {
        return $this->result;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function markInProgress(): void
    {
        $this->guardTransitionFrom(DelegationStatus::Pending, DelegationStatus::InProgress);
        $this->status = DelegationStatus::InProgress;
    }

    /**
     * @param array<string, mixed> $result
     */
    public function markCompleted(array $result): void
    {
        $this->guardTransitionFrom(DelegationStatus::InProgress, DelegationStatus::Completed);
        $this->status = DelegationStatus::Completed;
        $this->result = $result;
        $this->completedAt = new DateTimeImmutable();
    }

    public function markFailed(string $reason): void
    {
        $this->guardTransitionFrom(DelegationStatus::InProgress, DelegationStatus::Failed);
        $this->status = DelegationStatus::Failed;
        $this->result = ['error' => $reason];
        $this->completedAt = new DateTimeImmutable();
    }

    public function markTimeout(float $elapsedSeconds): void
    {
        $this->guardTransitionFrom(DelegationStatus::InProgress, DelegationStatus::Timeout);
        $this->status = DelegationStatus::Timeout;
        $this->result = [
            'error' => "Delegation exceeded its own {$this->timeoutSeconds}s timeout ({$elapsedSeconds}s elapsed).",
        ];
        $this->completedAt = new DateTimeImmutable();
    }

    private function guardTransitionFrom(DelegationStatus $expected, DelegationStatus $target): void
    {
        if ($this->status !== $expected) {
            throw new LogicException(
                "Cannot move DelegationRequest to [{$target->value}] from [{$this->status->value}], expected [{$expected->value}]."
            );
        }
    }
}
