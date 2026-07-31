<?php

namespace App\Modules\Workflows\Application\DTOs;

use App\Modules\Workflows\Domain\Entities\WorkflowLog;

/**
 * Structured data transfer for WorkflowLog across layers. Not part of
 * the original request's DTO list — added alongside the WorkflowLog
 * Entity itself (see that class's docblock) since `workflow.log.list`
 * needs a structured shape to return.
 */
final class WorkflowLogData
{
    /**
     * @param array<string, mixed> $eventData
     * @param list<array<string, mixed>> $actionsExecuted
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $workflowId,
        public readonly array $eventData,
        public readonly array $actionsExecuted,
        public readonly string $status,
    ) {
    }

    public static function fromEntity(WorkflowLog $log): self
    {
        return new self(
            id: $log->id(),
            tenantId: $log->tenantId(),
            workflowId: $log->workflowId(),
            eventData: $log->eventData(),
            actionsExecuted: $log->actionsExecuted(),
            status: $log->status(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'workflowId' => $this->workflowId,
            'eventData' => $this->eventData,
            'actionsExecuted' => $this->actionsExecuted,
            'status' => $this->status,
        ];
    }
}
