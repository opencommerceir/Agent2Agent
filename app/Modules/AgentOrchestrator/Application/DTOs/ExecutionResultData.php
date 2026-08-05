<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\ExecutionResult;

/**
 * The MCP/HTTP-facing shape of a finished goal execution — matches this
 * module's own documented `/api/agents/{agent_type}` response contract
 * (docs/agent-orchestrator.md) field-for-field, including its snake_case
 * top-level keys (`agent_type`/`execution_time`), a deliberate departure
 * from this codebase's usual camelCase DTO convention (`CapabilityData`,
 * `WarehouseData`, ...) — kept snake_case because that is this module's
 * own explicitly-requested wire contract, not an oversight.
 */
final class ExecutionResultData
{
    /**
     * @param list<ExecutionStepData> $steps
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $goal,
        public readonly string $agentType,
        public readonly array $steps,
        public readonly string $summary,
        public readonly string $status,
        public readonly float $executionTime,
    ) {
    }

    public static function fromEntity(ExecutionResult $result, ?int $id = null): self
    {
        return new self(
            id: $id,
            goal: $result->goal->text,
            agentType: $result->goal->agentType->value,
            steps: array_map(fn ($step) => ExecutionStepData::fromEntity($step), $result->steps),
            summary: $result->summary,
            status: $result->status,
            executionTime: round($result->executionTimeSeconds, 2),
        );
    }

    /**
     * @return array{id: ?int, goal: string, agent_type: string, steps: list<array>, summary: string, status: string, execution_time: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'goal' => $this->goal,
            'agent_type' => $this->agentType,
            'steps' => array_map(fn (ExecutionStepData $step) => $step->toArray(), $this->steps),
            'summary' => $this->summary,
            'status' => $this->status,
            'execution_time' => $this->executionTime,
        ];
    }
}
