<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Application\DTOs\WorkflowData;
use App\Modules\Workflows\Domain\Exceptions\WorkflowNotFoundException;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;

/**
 * Backs the `workflow.definition.get` MCP capability. Tenant-scoped by
 * WorkflowRepositoryInterface::findById() itself — an id belonging to a
 * different tenant reports the same WorkflowNotFoundException as an id
 * that never existed at all, the same tenant-isolation-by-omission shape
 * every other findById() in this codebase uses.
 */
final class GetWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
    ) {
    }

    public function execute(int $id, int $tenantId): WorkflowData
    {
        $workflow = $this->workflows->findById($id, $tenantId);

        if (! $workflow) {
            throw new WorkflowNotFoundException("Workflow [{$id}] does not exist.");
        }

        return WorkflowData::fromEntity($workflow);
    }
}
