<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Agent;

final class AgentData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $organizationId,
        public readonly string $name,
        public readonly string $type,
        public readonly string $status,
    ) {
    }

    public static function fromEntity(Agent $agent): self
    {
        return new self(
            id: $agent->id(),
            tenantId: $agent->tenantId(),
            organizationId: $agent->organizationId(),
            name: $agent->name(),
            type: $agent->type()->value,
            status: $agent->status()->value,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'organizationId' => $this->organizationId,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
        ];
    }
}
