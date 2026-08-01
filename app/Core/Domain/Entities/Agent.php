<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\AgentStatus;
use App\Core\Domain\ValueObjects\AgentType;
use DateTimeImmutable;

/**
 * Represents an AI Agent identity registered in the Agent Registry.
 *
 * Permission checks are never asked of this entity — Phase 3's
 * Role/Permission/MemberRole system (CheckPermissionAction) is the single
 * source of truth for "what can this agent do". An earlier version of this
 * entity embedded a raw `permissions` array directly on the Agent, which
 * became a second, unused, driftable source of truth once the Role system
 * existed — removed here rather than left dangling.
 */
final class Agent
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $organizationId,
        private string $name,
        private AgentType $type,
        private AgentStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        int $tenantId,
        int $organizationId,
        string $name,
        AgentType $type,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            organizationId: $organizationId,
            name: $name,
            type: $type,
            status: AgentStatus::Active,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function changeType(AgentType $type): void
    {
        $this->type = $type;
    }

    public function activate(): void
    {
        $this->status = AgentStatus::Active;
    }

    public function deactivate(): void
    {
        $this->status = AgentStatus::Inactive;
    }

    public function suspend(): void
    {
        $this->status = AgentStatus::Suspended;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function organizationId(): int
    {
        return $this->organizationId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): AgentType
    {
        return $this->type;
    }

    public function status(): AgentStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === AgentStatus::Active;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
