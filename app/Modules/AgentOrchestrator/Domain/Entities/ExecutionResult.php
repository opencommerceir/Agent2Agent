<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\StepStatus;

/**
 * The outcome of running one ExecutionPlan to completion. `status`/
 * `summary` are both derived, never caller-supplied — the same
 * "entity decides its own outcome from the facts it already holds" shape
 * `BulkOperation::complete()` already establishes (picking
 * Completed/Partial/Failed from its own final row counts, HANDOFF §7.23).
 *
 * `summary` is deliberately a generic completion report (step counts +
 * timing), never a domain-aware narrative like "created coupon SALE15 and
 * sent 500 notifications" — producing that would require this module to
 * understand what a `commerce.coupon.create`/`notification.message.send`
 * *output* actually means, which is exactly the business logic this
 * module must never contain (CLAUDE.md — "Never put business rules
 * inside... the MCP Layer"; this module sits in the same position). A
 * future LLM-based planner/summarizer is the natural place for a
 * domain-aware narrative summary — see docs/agent-orchestrator.md's own
 * roadmap section.
 */
final class ExecutionResult
{
    private function __construct(
        public readonly Goal $goal,
        public readonly array $steps,
        public readonly string $status,
        public readonly string $summary,
        public readonly float $executionTimeSeconds,
    ) {
    }

    /**
     * @param list<ExecutionStep> $steps already-executed (or empty-plan) steps
     */
    public static function fromSteps(Goal $goal, array $steps, float $executionTimeSeconds): self
    {
        $total = count($steps);
        $completed = 0;
        $failed = 0;

        foreach ($steps as $step) {
            match ($step->status()) {
                StepStatus::Completed => $completed++,
                StepStatus::Failed => $failed++,
                default => null,
            };
        }

        $status = match (true) {
            $total === 0 => 'empty',
            $failed === 0 => 'completed',
            $completed === 0 => 'failed',
            default => 'partial',
        };

        $summary = $total === 0
            ? 'No matching plan was found for this goal.'
            : sprintf(
                'Goal executed: %d of %d step(s) completed%s, in %.2fs.',
                $completed,
                $total,
                $failed > 0 ? ", {$failed} failed" : '',
                $executionTimeSeconds,
            );

        return new self($goal, $steps, $status, $summary, $executionTimeSeconds);
    }
}
