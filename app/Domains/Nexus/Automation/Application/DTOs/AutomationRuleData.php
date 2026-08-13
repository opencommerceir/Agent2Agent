<?php

namespace App\Domains\Nexus\Automation\Application\DTOs;

use App\Domains\Nexus\Automation\Domain\Entities\AutomationRule;

final class AutomationRuleData
{
    public function __construct(
        public readonly int $id,
        public readonly int $businessId,
        public readonly string $type,
        public readonly array $config,
        public readonly string $status,
        public readonly ?string $lastTriggeredAt,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(AutomationRule $rule): self
    {
        return new self(
            id: $rule->id(),
            businessId: $rule->businessId(),
            type: $rule->type()->value,
            config: $rule->config(),
            status: $rule->status()->value,
            lastTriggeredAt: $rule->lastTriggeredAt()?->format(DATE_ATOM),
            createdAt: $rule->createdAt()->format(DATE_ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessId' => $this->businessId,
            'type' => $this->type,
            'config' => $this->config,
            'status' => $this->status,
            'lastTriggeredAt' => $this->lastTriggeredAt,
            'createdAt' => $this->createdAt,
        ];
    }
}
