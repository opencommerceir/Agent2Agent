<?php

namespace App\Modules\Workflows\Application\DTOs;

use App\Modules\Workflows\Domain\Entities\Workflow;

/**
 * Structured data transfer for Workflow across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class WorkflowData
{
    /**
     * @param list<array<string, mixed>> $rules
     * @param list<array<string, mixed>> $actions
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $eventType,
        public readonly string $status,
        public readonly array $rules,
        public readonly array $actions,
    ) {
    }

    public static function fromEntity(Workflow $workflow): self
    {
        return new self(
            id: $workflow->id(),
            tenantId: $workflow->tenantId(),
            name: $workflow->name(),
            description: $workflow->description(),
            eventType: $workflow->eventType()->value,
            status: $workflow->status()->value,
            rules: array_map(
                fn ($rule) => WorkflowRuleData::fromEntity($rule)->toArray(),
                $workflow->rules(),
            ),
            actions: array_map(
                fn ($action) => WorkflowActionData::fromEntity($action)->toArray(),
                $workflow->actions(),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description,
            'eventType' => $this->eventType,
            'status' => $this->status,
            'rules' => $this->rules,
            'actions' => $this->actions,
        ];
    }
}
