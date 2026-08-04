<?php

namespace App\Modules\Commerce\Application\DTOs;

use App\Modules\Commerce\Domain\Entities\DiscountRule;

final class DiscountRuleData
{
    /**
     * @param list<DiscountConditionData> $conditions
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $discountType,
        public readonly int $discountValue,
        public readonly int $priority,
        public readonly string $stackability,
        public readonly array $conditions,
        public readonly string $startsAt,
        public readonly ?string $expiresAt,
        public readonly bool $isActive,
        public readonly ?int $maxUses,
        public readonly int $usedCount,
    ) {
    }

    public static function fromEntity(DiscountRule $rule): self
    {
        return new self(
            id: $rule->id(),
            tenantId: $rule->tenantId(),
            name: $rule->name(),
            description: $rule->description(),
            discountType: $rule->discountType()->value,
            discountValue: $rule->discountValue(),
            priority: $rule->priority()->value(),
            stackability: $rule->stackability()->value,
            conditions: array_map(
                fn ($condition) => DiscountConditionData::fromEntity($condition),
                $rule->conditions(),
            ),
            startsAt: $rule->startsAt()->format(DATE_ATOM),
            expiresAt: $rule->expiresAt()?->format(DATE_ATOM),
            isActive: $rule->isActive(),
            maxUses: $rule->maxUses(),
            usedCount: $rule->usedCount(),
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
            'discountType' => $this->discountType,
            'discountValue' => $this->discountValue,
            'priority' => $this->priority,
            'stackability' => $this->stackability,
            'conditions' => array_map(fn (DiscountConditionData $c) => $c->toArray(), $this->conditions),
            'startsAt' => $this->startsAt,
            'expiresAt' => $this->expiresAt,
            'isActive' => $this->isActive,
            'maxUses' => $this->maxUses,
            'usedCount' => $this->usedCount,
        ];
    }
}
