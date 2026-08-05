<?php

namespace App\Modules\AgentOrchestrator\Domain\Entities;

use App\Modules\AgentOrchestrator\Domain\ValueObjects\AgentType;
use InvalidArgumentException;

/**
 * A business objective stated in plain text (e.g. "Increase sales by 15%
 * this week"). Deliberately just a text + classification pair, not a
 * parsed/structured intent — turning free text into a structured intent
 * is exactly what PlannerInterface exists to do, and doing any of that
 * parsing here would leak planning (a Domain Service's job) into the
 * Entity itself.
 */
final class Goal
{
    private function __construct(
        public readonly string $text,
        public readonly AgentType $agentType,
    ) {
    }

    public static function fromText(string $text, AgentType $agentType): self
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Goal text cannot be empty.');
        }

        return new self($trimmed, $agentType);
    }
}
