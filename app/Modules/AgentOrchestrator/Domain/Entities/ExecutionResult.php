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

    /**
     * A full success only — every planned step completed, none failed.
     * `'partial'` is deliberately not success here (Phase 6, Stage 4,
     * §7.29's own PatternExtractor only ever learns from a run where the
     * *whole* plan worked; a partially-failed run is not a pattern worth
     * repeating). Matches `status === 'completed'` exactly — added as a
     * named method rather than repeating that string comparison in every
     * new §7.29 class that needs it.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Capability names for every step that actually completed, in plan
     * order, duplicates included (order/repetition matters for a future
     * pattern's own "these ran together" signal — HANDOFF §7.29). Never
     * includes a Skipped/Pending step, and never a Failed one — see
     * `failedCapabilities()` for those.
     *
     * @return list<string>
     */
    public function successfulCapabilities(): array
    {
        return array_values(array_map(
            fn (ExecutionStep $step) => $step->capability,
            array_filter($this->steps, fn (ExecutionStep $step) => $step->status() === StepStatus::Completed),
        ));
    }

    /**
     * @return list<string>
     */
    public function failedCapabilities(): array
    {
        return array_values(array_map(
            fn (ExecutionStep $step) => $step->capability,
            array_filter($this->steps, fn (ExecutionStep $step) => $step->status() === StepStatus::Failed),
        ));
    }
}
