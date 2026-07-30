<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\AgentStatus;
use App\Core\Domain\ValueObjects\AgentType;
use DateTimeImmutable;

/**
 * Represents an AI Agent identity registered in the Agent Registry.
 * Permissions are stored as capability strings (e.g. "commerce.products.read")
 * resolved against the Permission Layer at authorization time.
 *
 * Tenant isolation is carried as data (tenantId) but never enforced here —
 * enforcing it is an authorization decision that belongs to the Application
 * layer, not the Domain entity or the Repository (Repository Conventions).
 */
final class Agent
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly int $organizationId,
        private string $name,
        private AgentType $type,
        private array $permissions,
        private AgentStatus $status,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<string> $permissions
     */
    public static function register(
        int $tenantId,
        int $organizationId,
        string $name,
        AgentType $type,
        array $permissions = [],
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            organizationId: $organizationId,
            name: $name,
            type: $type,
            permissions: $permissions,
            status: AgentStatus::Active,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function grant(string $permission): void
    {
        if (! in_array($permission, $this->permissions, true)) {
            $this->permissions[] = $permission;
        }
    }

    public function revoke(string $permission): void
    {
        $this->permissions = array_values(
            array_filter($this->permissions, fn (string $p) => $p !== $permission)
        );
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
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

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
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
