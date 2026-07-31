<?php

namespace App\Modules\Workflows\Application\DTOs;

use App\Modules\Workflows\Domain\Entities\WorkflowAction;

/**
 * Structured data transfer for WorkflowAction across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class WorkflowActionData
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        public readonly string $actionType,
        public readonly array $parameters,
    ) {
    }

    public static function fromEntity(WorkflowAction $action): self
    {
        return new self(
            actionType: $action->actionType(),
            parameters: $action->parameters(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'actionType' => $this->actionType,
            'parameters' => $this->parameters,
        ];
    }
}
