<?php

namespace App\Modules\Workflows\Domain\Entities;

use DateTimeImmutable;

/**
 * A record of one Workflow trigger attempt — not part of the original
 * request's Entity list, added because `workflow_logs` (a requested
 * table) and `workflow.log.list` (a requested capability) both need a
 * structured way to represent one log row, and the request named exactly
 * one Repository interface (`WorkflowRepositoryInterface`) for the whole
 * module. Same "added unprompted, well-justified" pattern as Finance's
 * `OrderNotFoundException`/CRM's `TagNotFoundException` — this Entity
 * plus `WorkflowRepositoryInterface::saveLog()`/`listLogs()` (rather than
 * a second, dedicated `WorkflowLogRepositoryInterface`) is how this
 * module's execution history is owned by the same aggregate whose logs
 * they are, the same "repository interface owns its child records" shape
 * CRM's `TicketRepositoryInterface` (owns `TicketComment`) and Finance's
 * `InvoiceRepositoryInterface` (owns `InvoiceItem`) already established.
 *
 * Immutable — a log entry is written once by `TriggerWorkflowAction` and
 * never edited.
 */
final class WorkflowLog
{
    /**
     * @param array<string, mixed> $eventData
     * @param list<array<string, mixed>> $actionsExecuted
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $workflowId,
        private readonly array $eventData,
        private readonly array $actionsExecuted,
        private readonly string $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $eventData
     * @param list<array<string, mixed>> $actionsExecuted
     */
    public static function create(
        int $tenantId,
        int $workflowId,
        array $eventData,
        array $actionsExecuted,
        string $status,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            workflowId: $workflowId,
            eventData: $eventData,
            actionsExecuted: $actionsExecuted,
            status: $status,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function workflowId(): int
    {
        return $this->workflowId;
    }

    /**
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        return $this->eventData;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actionsExecuted(): array
    {
        return $this->actionsExecuted;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
