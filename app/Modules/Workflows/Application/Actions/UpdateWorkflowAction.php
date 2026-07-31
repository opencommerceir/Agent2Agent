<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Application\DTOs\WorkflowData;
use App\Modules\Workflows\Domain\Exceptions\WorkflowNotFoundException;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;

/**
 * Rules/actions are deliberately not updatable here — Workflow's own
 * docblock explains why (the same reasoning Product's SKU is immutable
 * after creation). Not wired to MCP this stage — no
 * `workflow.definition.update`-shaped capability was among the 5
 * requested. Exercised directly in tests instead, the same "built,
 * tested, not yet exposed to Agents" gap several Commerce/CRM/Finance
 * Actions already carry.
 */
final class UpdateWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
    ) {
    }

    public function execute(int $id, int $tenantId, string $name, ?string $description, string $status): WorkflowData
    {
        $workflow = $this->workflows->findById($id, $tenantId);

        if (! $workflow) {
            throw new WorkflowNotFoundException("Workflow [{$id}] does not exist.");
        }

        $workflow->update($name, $description, WorkflowStatus::from($status));

        $workflow = $this->workflows->save($workflow);

        return WorkflowData::fromEntity($workflow);
    }
}
