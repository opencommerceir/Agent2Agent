<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ConfidenceScore;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\ReasoningType;
use DateTimeImmutable;
use LogicException;

/**
 * One "thought" a `ReasoningEngineInterface` produced — either `think()`ing
 * before a Plan is created, or `reflect()`ing once a real `ExecutionResult`
 * exists (Phase 6, Stage 6, §7.31). Append-only, like `AgentMessage`
 * (§7.30) — a trace is never edited after it's produced, only ever
 * inserted, hence the identical `id()`/`assignId()` one-time-mutator shape.
 *
 * `executionId` is `?int`, not `int`, for a real structural reason: a
 * `PreExecution` trace is produced *before* `ExecuteGoalAction` has a real
 * execution id at all (`ExecutionResult` itself carries no id — see
 * `ExecutionMemoryRepositoryInterface::save()`'s own docblock; the int id
 * only exists once that call returns). Rather than inventing a way to
 * patch an already-persisted row's `execution_id` later, `ExecuteGoalAction`
 * holds the `PreExecution` trace in memory until the real id is known, calls
 * `assignExecutionId()` once, and only then persists both this trace and
 * the `PostExecution` one together — so `ReasoningTraceRepositoryInterface::save()`
 * only ever inserts a trace whose `executionId()` is already non-null; see
 * that Interface's own docblock.
 *
 * Reasoning here is deliberately *explanatory, not plan-changing* — a
 * `ReasoningTrace`'s own `decision`/`alternatives` document why a plan was
 * chosen, but neither `PlannerInterface` nor `PlanExecutorInterface` reads
 * anything off this Entity; the capability sequence that actually runs is
 * decided exactly the same way it always was (learned pattern, then
 * Planner). The same restraint §7.30 already established for
 * `agent.collaboration.delegate` (a real mechanism that never automatically
 * reroutes an in-flight plan) — see `docs/self-reflection.md`.
 */
final class ReasoningTrace
{
    private ?int $id;

    private ?int $executionId;

    /**
     * @param list<string> $thoughts
     * @param list<\App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan> $alternatives
     */
    private function __construct(
        ?int $id,
        ?int $executionId,
        public readonly int $tenantId,
        public readonly AgentType $agentType,
        public readonly string $goalText,
        public readonly ReasoningType $reasoningType,
        public readonly array $thoughts,
        public readonly array $alternatives,
        public readonly ConfidenceScore $confidenceScore,
        public readonly string $decision,
        public readonly string $explanation,
        public readonly DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->executionId = $executionId;
    }

    /**
     * @param list<string> $thoughts
     * @param list<\App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan> $alternatives
     */
    public static function create(
        int $tenantId,
        AgentType $agentType,
        string $goalText,
        ReasoningType $reasoningType,
        array $thoughts,
        array $alternatives,
        ConfidenceScore $confidenceScore,
        string $decision,
        string $explanation,
        ?int $executionId = null,
    ): self {
        return new self(
            id: null,
            executionId: $executionId,
            tenantId: $tenantId,
            agentType: $agentType,
            goalText: $goalText,
            reasoningType: $reasoningType,
            thoughts: $thoughts,
            alternatives: $alternatives,
            confidenceScore: $confidenceScore,
            decision: $decision,
            explanation: $explanation,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param list<string> $thoughts
     * @param list<\App\Modules\AgentOrchestrator\Domain\ValueObjects\AlternativePlan> $alternatives
     */
    public static function reconstruct(
        int $id,
        int $tenantId,
        AgentType $agentType,
        string $goalText,
        ReasoningType $reasoningType,
        array $thoughts,
        array $alternatives,
        ConfidenceScore $confidenceScore,
        string $decision,
        string $explanation,
        int $executionId,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            executionId: $executionId,
            tenantId: $tenantId,
            agentType: $agentType,
            goalText: $goalText,
            reasoningType: $reasoningType,
            thoughts: $thoughts,
            alternatives: $alternatives,
            confidenceScore: $confidenceScore,
            decision: $decision,
            explanation: $explanation,
            createdAt: $createdAt,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new LogicException("ReasoningTrace already has id [{$this->id}]; assignId() is one-time only.");
        }

        $this->id = $id;
    }

    public function executionId(): ?int
    {
        return $this->executionId;
    }

    public function assignExecutionId(int $executionId): void
    {
        if ($this->executionId !== null) {
            throw new LogicException(
                "ReasoningTrace already has executionId [{$this->executionId}]; assignExecutionId() is one-time only."
            );
        }

        $this->executionId = $executionId;
    }
}
