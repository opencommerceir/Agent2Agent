<?php

namespace App\Modules\Workflows\Domain\Repositories;

use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Entities\WorkflowLog;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;

/**
 * Contract owned by the Domain layer (Interfaces Over Tight Coupling).
 * Every method takes tenantId explicitly — never inferred from ambient
 * state. Owns WorkflowLog persistence too (saveLog()/listLogs()) — a log
 * has no meaning detached from the Workflow it logs, the same "no
 * separate repository for a child record" reasoning CRM's
 * TicketRepositoryInterface (owns TicketComment) and Finance's
 * InvoiceRepositoryInterface (owns InvoiceItem) already established.
 */
interface WorkflowRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Workflow;

    /**
     * @return list<Workflow>
     */
    public function list(int $tenantId, ?WorkflowStatus $status, ?EventType $eventType): array;

    /**
     * Active Workflows for one tenant matching one EventType — what
     * TriggerWorkflowAction fans out over, whether the trigger came from
     * a real Domain Event Listener or a direct `workflow.event.trigger`
     * MCP call.
     *
     * @return list<Workflow>
     */
    public function findActiveByEventType(EventType $eventType, int $tenantId): array;

    public function save(Workflow $workflow): Workflow;

    public function saveLog(WorkflowLog $log): WorkflowLog;

    /**
     * @return list<WorkflowLog>
     */
    public function listLogs(int $tenantId, ?int $workflowId, int $limit): array;
}
