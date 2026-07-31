<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Application\DTOs\WorkflowData;
use App\Modules\Workflows\Domain\Entities\Workflow;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;
use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;

/**
 * Backs the `workflow.definition.list` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus tenantId, the same pattern
 * every other List*Action in this codebase established.
 */
final class ListWorkflowsAction
{
    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{workflows: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $status = isset($input['status']) && is_string($input['status'])
            ? WorkflowStatus::tryFrom($input['status'])
            : null;

        $eventType = isset($input['event_type']) && is_string($input['event_type'])
            ? EventType::tryFrom($input['event_type'])
            : null;

        $workflows = $this->workflows->list($tenantId, $status, $eventType);

        return [
            'workflows' => array_map(
                fn (Workflow $workflow) => WorkflowData::fromEntity($workflow)->toArray(),
                $workflows,
            ),
        ];
    }
}
