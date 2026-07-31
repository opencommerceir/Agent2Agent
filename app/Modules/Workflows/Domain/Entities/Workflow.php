<?php

namespace App\Modules\Workflows\Domain\Entities;

use App\Modules\Workflows\Domain\ValueObjects\EventType;
use App\Modules\Workflows\Domain\ValueObjects\WorkflowStatus;
use DateTimeImmutable;

/**
 * A tenant-defined "when X happens and Y is true, do Z" automation.
 * Rules and actions are frozen at creation (same Immutable Order Items
 * reasoning Order/Invoice already establish for their own line items) —
 * `UpdateWorkflowAction` can change name/description/status, never the
 * rules/actions themselves; redefining what a Workflow actually checks
 * or does would be a distinct, more deliberate operation than a generic
 * field update (the exact reasoning Product's SKU/Category's slug are
 * immutable-after-creation for).
 */
final class Workflow
{
    /**
     * @param list<WorkflowRule> $rules
     * @param list<WorkflowAction> $actions
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private string $name,
        private ?string $description,
        private readonly EventType $eventType,
        private WorkflowStatus $status,
        private readonly array $rules,
        private readonly array $actions,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<WorkflowRule> $rules
     * @param list<WorkflowAction> $actions
     */
    public static function create(
        int $tenantId,
        string $name,
        ?string $description,
        EventType $eventType,
        array $rules,
        array $actions,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            description: $description,
            eventType: $eventType,
            status: WorkflowStatus::Active,
            rules: $rules,
            actions: $actions,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function update(string $name, ?string $description, WorkflowStatus $status): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
    }

    public function isActive(): bool
    {
        return $this->status === WorkflowStatus::Active;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function eventType(): EventType
    {
        return $this->eventType;
    }

    public function status(): WorkflowStatus
    {
        return $this->status;
    }

    /**
     * @return list<WorkflowRule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * @return list<WorkflowAction>
     */
    public function actions(): array
    {
        return $this->actions;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
