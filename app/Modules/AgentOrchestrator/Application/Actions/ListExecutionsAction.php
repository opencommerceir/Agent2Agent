<?php

namespace App\Modules\AgentOrchestrator\Application\Actions;

use App\Modules\AgentOrchestrator\Application\DTOs\ExecutionResultData;
use App\Modules\AgentOrchestrator\Domain\Repositories\ExecutionMemoryRepositoryInterface;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;

final class ListExecutionsAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly ExecutionMemoryRepositoryInterface $memory,
    ) {
    }

    /**
     * @return list<ExecutionResultData>
     */
    public function execute(int $tenantId, ?AgentType $agentType = null, ?string $status = null, ?int $limit = null): array
    {
        $safeLimit = $limit !== null ? max(1, min($limit, self::MAX_LIMIT)) : self::DEFAULT_LIMIT;

        return array_map(
            fn (array $record) => ExecutionResultData::fromEntity(
                $record['result'],
                $record['id'],
                createdAt: $record['createdAt'] ?? null,
            ),
            $this->memory->list($tenantId, $agentType, $status, $safeLimit),
        );
    }
}
