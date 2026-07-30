<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Role;

interface RoleRepositoryInterface
{
    public function findById(int $id): ?Role;

    public function findBySlug(int $tenantId, string $slug): ?Role;

    public function save(Role $role): Role;

    /**
     * Relationship management on the role_permissions pivot. Deliberately
     * on the Repository (persistence-adjacent), not a Role entity mutator —
     * see the note on Role::$permissions.
     */
    public function assignPermission(int $roleId, int $permissionId): void;

    public function revokePermission(int $roleId, int $permissionId): void;
}
