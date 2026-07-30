<?php

namespace App\Core\Domain\Entities;

use App\Core\Domain\ValueObjects\PermissionKey;
use DateTimeImmutable;

/**
 * A tenant-defined, named bundle of Permissions (e.g. "store_manager").
 *
 * $permissions is a read projection populated by the Repository when it
 * loads a Role — it is not mutated directly here. Granting/revoking a
 * permission is a persistence-adjacent operation on the role_permissions
 * pivot (see RoleRepositoryInterface::assignPermission/revokePermission),
 * not a pure in-memory Entity mutation, since the pivot row is the actual
 * source of truth.
 */
final class Role
{
    /**
     * @param list<PermissionKey> $permissions
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private string $name,
        private string $slug,
        private ?string $description,
        private readonly array $permissions,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(int $tenantId, string $name, string $slug, ?string $description = null): self
    {
        return new self(
            id: null,
            tenantId: $tenantId,
            name: $name,
            slug: $slug,
            description: $description,
            permissions: [],
            createdAt: new DateTimeImmutable(),
        );
    }

    public function hasPermission(PermissionKey $key): bool
    {
        foreach ($this->permissions as $permission) {
            if ($permission->equals($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<PermissionKey>
     */
    public function permissions(): array
    {
        return $this->permissions;
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

    public function slug(): string
    {
        return $this->slug;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
