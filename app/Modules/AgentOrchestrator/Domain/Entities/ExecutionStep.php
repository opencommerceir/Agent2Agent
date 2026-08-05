<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\Priority;
use App\Modules\AgentOrchestrator\Domain\ValueObjects\StepStatus;
use LogicException;

/**
 * One planned invocation of exactly one MCP capability — `capability` +
 * `input` are fixed at construction (set by a Planner); `status`/`output`/
 * `errorMessage` are the only mutable state, moved through by
 * PlanExecutor as it runs. Mirrors `Order`'s own state-machine shape
 * (mutators guard against an illegal transition) rather than
 * OrderItem/Discount's fully-immutable shape, since — unlike those —
 * this Entity's whole purpose is to record what happened *during* one
 * execution, not to freeze a fact that already happened before it existed.
 */
final class ExecutionStep
{
    private StepStatus $status;

    private ?array $output = null;

    private ?string $errorMessage = null;

    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly string $capability,
        public readonly array $input,
        public readonly Priority $priority = Priority::Medium,
    ) {
        $this->status = StepStatus::Pending;
    }

    public function status(): StepStatus
    {
        return $this->status;
    }

    /**
     * @return ?array<string, mixed>
     */
    public function output(): ?array
    {
        return $this->output;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function markAsRunning(): void
    {
        if ($this->status !== StepStatus::Pending) {
            throw new LogicException("Cannot start a step that is already [{$this->status->value}].");
        }

        $this->status = StepStatus::Running;
    }

    /**
     * @param array<string, mixed> $output
     */
    public function markAsCompleted(array $output): void
    {
        $this->output = $output;
        $this->status = StepStatus::Completed;
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
        $this->status = StepStatus::Failed;
    }

    public function markAsSkipped(): void
    {
        $this->status = StepStatus::Skipped;
    }

    /**
     * Rebuilds a step directly into an already-terminal state, for
     * `EloquentExecutionMemoryRepository::toResult()` alone — persisted
     * history is read back as the fact it already is, not replayed
     * through `markAsRunning()`/`markAsCompleted()`'s own transition
     * guards a second time. The same "toEntity() reconstructs directly,
     * business methods are for actual transitions" split every other
     * Eloquent Repository's own `toEntity()` in this codebase already
     * relies on.
     *
     * @param ?array<string, mixed> $output
     */
    public static function reconstruct(
        string $capability,
        array $input,
        Priority $priority,
        StepStatus $status,
        ?array $output,
        ?string $errorMessage,
    ): self {
        $step = new self($capability, $input, $priority);
        $step->status = $status;
        $step->output = $output;
        $step->errorMessage = $errorMessage;

        return $step;
    }
}
