<?php

namespace App\Modules\AgentOrchestrator\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * How sure a `ReasoningTrace` is about its own `decision` (Phase 6, Stage
 * 6, §7.31) — a plain 0.0-1.0 fraction, the same "validated float wrapper"
 * shape `DelegationPriority`'s own 1-10 int wrapper already establishes,
 * one level narrower. `LLMReasoningEngine` reads this straight from the
 * LLM's own structured response; `SimpleReasoningEngine`'s deterministic
 * fallback derives it from real signals it actually has (a matched
 * `ExecutionPattern`'s own `successRate()` when reasoning before
 * execution, the real `ExecutionResult::successRate()` when reflecting
 * after) rather than a made-up constant.
 */
final class ConfidenceScore
{
    private const MIN = 0.0;

    private const MAX = 1.0;

    private function __construct(
        public readonly float $value,
    ) {
    }

    public static function fromFloat(float $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidArgumentException(
                "Confidence score must be between ".self::MIN." and ".self::MAX.", got [{$value}]."
            );
        }

        return new self($value);
    }

    public function asPercentage(): float
    {
        return round($this->value * 100, 1);
    }
}
