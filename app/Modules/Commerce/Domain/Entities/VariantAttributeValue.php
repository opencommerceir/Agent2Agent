<?php

namespace App\Modules\Commerce\Domain\Entities;

use DateTimeImmutable;

/**
 * One selectable value of a VariantAttribute (e.g. "Red" under "Color").
 * No `tenantId` of its own — inherited through `attributeId`, the same
 * shape `OrderItem`/`TicketComment`/`WorkflowRule` already have relative
 * to their own parent (HANDOFF's own recurring convention for a child
 * entity with no independent identity beyond its parent). Frozen after
 * creation — no mutators, no "rename this value" operation (§8.25's own
 * precedent, Workflows' rules/actions).
 */
final class VariantAttributeValue
{
    private function __construct(
        private readonly ?int $id,
        private readonly ?int $attributeId,
        private readonly string $value,
        private readonly int $displayOrder,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $value, int $displayOrder = 0, ?int $attributeId = null): self
    {
        return new self(
            id: null,
            attributeId: $attributeId,
            value: $value,
            displayOrder: $displayOrder,
            createdAt: new DateTimeImmutable(),
        );
    }

    public static function reconstitute(?int $id, ?int $attributeId, string $value, int $displayOrder, DateTimeImmutable $createdAt): self
    {
        return new self($id, $attributeId, $value, $displayOrder, $createdAt);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function attributeId(): ?int
    {
        return $this->attributeId;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
