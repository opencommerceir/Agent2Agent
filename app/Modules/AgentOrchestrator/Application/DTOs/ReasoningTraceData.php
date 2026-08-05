<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\ReasoningTrace;

/**
 * The MCP/HTTP-facing shape of one `ReasoningTrace` — snake_case
 * `toArray()`, the same convention `AgentMessageData`/`ExecutionResultData`
 * already establish for this module's own wire contract.
 */
final class ReasoningTraceData
{
    /**
     * @param list<string> $thoughts
     * @param list<array{plan: string, confidence: float, reason: string}> $alternatives
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $executionId,
        public readonly string $agentType,
        public readonly string $goalText,
        public readonly string $reasoningType,
        public readonly array $thoughts,
        public readonly array $alternatives,
        public readonly float $confidenceScore,
        public readonly string $decision,
        public readonly string $explanation,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(ReasoningTrace $trace): self
    {
        return new self(
            id: $trace->id(),
            executionId: $trace->executionId(),
            agentType: $trace->agentType->value,
            goalText: $trace->goalText,
            reasoningType: $trace->reasoningType->value,
            thoughts: $trace->thoughts,
            alternatives: array_map(fn ($alternative) => $alternative->toArray(), $trace->alternatives),
            confidenceScore: $trace->confidenceScore->value,
            decision: $trace->decision,
            explanation: $trace->explanation,
            createdAt: $trace->createdAt->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: ?int, execution_id: ?int, agent_type: string, goal_text: string, reasoning_type: string, thoughts: list<string>, alternatives: array, confidence_score: float, decision: string, explanation: string, created_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'execution_id' => $this->executionId,
            'agent_type' => $this->agentType,
            'goal_text' => $this->goalText,
            'reasoning_type' => $this->reasoningType,
            'thoughts' => $this->thoughts,
            'alternatives' => $this->alternatives,
            'confidence_score' => $this->confidenceScore,
            'decision' => $this->decision,
            'explanation' => $this->explanation,
            'created_at' => $this->createdAt,
        ];
    }
}
