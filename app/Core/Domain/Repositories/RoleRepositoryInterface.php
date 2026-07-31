<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\Role;

interface RoleRepositoryInterface
{
    public function findById(int $id): ?Role;

    /**
     * Batch lookup by id, permissions eager-loaded in the same query —
     * exists specifically so findRolesForMember() can resolve N role ids
     * in one round trip instead of calling findById() in a loop (the
     * fixed N+1: see EloquentRoleRepository::findByIds()'s own docblock).
     *
     * @param list<int> $ids
     * @return list<Role>
     */
    public function findByIds(array $ids): array;

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
