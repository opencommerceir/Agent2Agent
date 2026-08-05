<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

/**
 * One approach a `ReasoningTrace` considered but did not choose (Phase 6,
 * Stage 6, §7.31) — `plan` is a short, human-readable description, never a
 * real `ExecutionPlan`/capability sequence; this stage's reasoning is
 * explanatory only (see `ReasoningTrace`'s own docblock for why an
 * alternative never actually changes which capabilities run). Only ever
 * populated on a `PreExecution` trace — `reflect()` never proposes
 * alternatives to a plan that has already run.
 */
final class AlternativePlan
{
    private function __construct(
        public readonly string $plan,
        public readonly ConfidenceScore $confidence,
        public readonly string $reason,
    ) {
    }

    public static function create(string $plan, float $confidence, string $reason): self
    {
        return new self($plan, ConfidenceScore::fromFloat($confidence), $reason);
    }

    /**
     * @return array{plan: string, confidence: float, reason: string}
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan,
            'confidence' => $this->confidence->value,
            'reason' => $this->reason,
        ];
    }
}
