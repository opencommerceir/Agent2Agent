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
 *
 * `preReasoning`/`postReasoning`/`explanation` are new, optional trailing
 * fields (Phase 6, Stage 6, §7.31 — HANDOFF §3 pattern #6, "widen with
 * optional trailing parameters," the same shape `Order::place()` grew
 * `tax`/`discount`/`total` across Stages 3-5) — every pre-existing caller
 * of `fromEntity()`/the constructor that doesn't pass them is unaffected;
 * `ExecuteGoalAction` is the only caller that does.
 */
final class ExecutionResultData
{
    /**
     * @param list<ExecutionStepData> $steps
     * @param ?array<string, mixed> $preReasoning
     * @param ?array<string, mixed> $postReasoning
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $goal,
        public readonly string $agentType,
        public readonly array $steps,
        public readonly string $summary,
        public readonly string $status,
        public readonly float $executionTime,
        public readonly ?array $preReasoning = null,
        public readonly ?array $postReasoning = null,
        public readonly ?string $explanation = null,
    ) {
    }

    /**
     * @param ?array<string, mixed> $preReasoning
     * @param ?array<string, mixed> $postReasoning
     */
    public static function fromEntity(
        ExecutionResult $result,
        ?int $id = null,
        ?array $preReasoning = null,
        ?array $postReasoning = null,
        ?string $explanation = null,
    ): self {
        return new self(
            id: $id,
            goal: $result->goal->text,
            agentType: $result->goal->agentType->value,
            steps: array_map(fn ($step) => ExecutionStepData::fromEntity($step), $result->steps),
            summary: $result->summary,
            status: $result->status,
            executionTime: round($result->executionTimeSeconds, 2),
            preReasoning: $preReasoning,
            postReasoning: $postReasoning,
            explanation: $explanation,
        );
    }

    /**
     * @return array{id: ?int, goal: string, agent_type: string, steps: list<array>, summary: string, status: string, execution_time: float, pre_reasoning: ?array, post_reasoning: ?array, explanation: ?string}
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
            'pre_reasoning' => $this->preReasoning,
            'post_reasoning' => $this->postReasoning,
            'explanation' => $this->explanation,
        ];
    }
}
