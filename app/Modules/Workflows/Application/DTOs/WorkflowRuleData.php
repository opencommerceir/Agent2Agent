<?php

namespace App\Modules\Workflows\Application\DTOs;

use App\Modules\Workflows\Domain\Entities\WorkflowRule;

/**
 * Structured data transfer for WorkflowRule across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class WorkflowRuleData
{
    public function __construct(
        public readonly string $conditionType,
        public readonly string $field,
        public readonly int $thresholdValue,
    ) {
    }

    public static function fromEntity(WorkflowRule $rule): self
    {
        return new self(
            conditionType: $rule->conditionType(),
            field: $rule->field(),
            thresholdValue: $rule->threshold()->value(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'conditionType' => $this->conditionType,
            'field' => $this->field,
            'thresholdValue' => $this->thresholdValue,
        ];
    }
}
