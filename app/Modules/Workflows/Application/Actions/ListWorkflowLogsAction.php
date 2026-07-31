<?php

namespace App\Modules\Workflows\Application\Actions;

use App\Modules\Workflows\Application\DTOs\WorkflowLogData;
use App\Modules\Workflows\Domain\Entities\WorkflowLog;
use App\Modules\Workflows\Domain\Repositories\WorkflowRepositoryInterface;

/**
 * Backs the `workflow.log.list` MCP capability. Not part of the original
 * request's Action list — added alongside the WorkflowLog Entity/DTO
 * (see WorkflowLog's own docblock) since the requested capability needs
 * some Action behind it, and none of the 6 named ones fit.
 */
final class ListWorkflowLogsAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly WorkflowRepositoryInterface $workflows,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{logs: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $workflowId = isset($input['workflow_id']) ? (int) $input['workflow_id'] : null;

        $limit = isset($input['limit']) && is_int($input['limit'])
            ? max(1, min($input['limit'], self::MAX_LIMIT))
            : self::DEFAULT_LIMIT;

        $logs = $this->workflows->listLogs($tenantId, $workflowId, $limit);

        return [
            'logs' => array_map(
                fn (WorkflowLog $log) => WorkflowLogData::fromEntity($log)->toArray(),
                $logs,
            ),
        ];
    }
}
