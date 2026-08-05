<?php

namespace App\Modules\AgentOrchestrator\Application\DTOs;

use App\Modules\AgentOrchestrator\Domain\Entities\Goal;

final class GoalData
{
    public function __construct(
        public readonly string $text,
        public readonly string $agentType,
    ) {
    }

    public static function fromEntity(Goal $goal): self
    {
        return new self($goal->text, $goal->agentType->value);
    }

    /**
     * @return array{text: string, agentType: string}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'agentType' => $this->agentType,
        ];
    }
}
