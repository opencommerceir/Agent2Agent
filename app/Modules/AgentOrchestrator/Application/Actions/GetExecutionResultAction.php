<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Exceptions\ExecutionNotFoundException;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;

final class GetExecutionResultAction
{
    public function __construct(
        private readonly ExecutionMemoryRepositoryInterface $memory,
    ) {
    }

    public function execute(int $executionId, int $tenantId): ExecutionResultData
    {
        $record = $this->memory->findById($executionId, $tenantId);

        if ($record === null) {
            throw new ExecutionNotFoundException("Execution [{$executionId}] does not exist.");
        }

        return ExecutionResultData::fromEntity($record['result'], $record['id'], createdAt: $record['createdAt'] ?? null);
    }
}
