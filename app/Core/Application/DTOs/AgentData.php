<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Agent;

/**
 * Structured data transfer for Agent across layers.
 * Represents data only — no business logic (DTO Conventions).
 */
final class AgentData
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly int $organizationId,
        public readonly string $name,
        public readonly string $type,
        public readonly string $status,
        public readonly array $permissions,
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
            permissions: $agent->permissions(),
        );
    }

    /**
     * @return array{id: ?int, tenantId: int, organizationId: int, name: string, type: string, status: string, permissions: list<string>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'organizationId' => $this->organizationId,
            'name' => $this->name,
            'type' => $this->type,
            'status' => $this->status,
            'permissions' => $this->permissions,
        ];
    }
}
