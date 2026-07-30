<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\RoleData;
use App\Core\Domain\Events\PermissionRevokedFromRole;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\Repositories\RoleRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class RevokePermissionFromRoleAction
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly PermissionRepositoryInterface $permissions,
    ) {
    }

    public function execute(int $roleId, int $permissionId): RoleData
    {
        $role = $this->roles->findById($roleId);
        $permission = $this->permissions->findById($permissionId);

        if (! $role || ! $permission) {
            throw new InvalidArgumentException('Role or Permission not found.');
        }

        $this->roles->revokePermission($roleId, $permissionId);

        Event::dispatch(new PermissionRevokedFromRole($role, $permission));

        return RoleData::fromEntity($this->roles->findById($roleId));
    }
}
