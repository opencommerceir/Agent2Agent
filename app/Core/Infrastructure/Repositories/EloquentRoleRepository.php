<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\Role as RoleEntity;
use App\Core\Domain\Repositories\RoleRepositoryInterface;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Core\Infrastructure\Models\Role as RoleModel;
use DateTimeImmutable;

class EloquentRoleRepository implements RoleRepositoryInterface
{
    public function findById(int $id): ?RoleEntity
    {
        $model = RoleModel::with('permissions')->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySlug(int $tenantId, string $slug): ?RoleEntity
    {
        $model = RoleModel::with('permissions')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(RoleEntity $role): RoleEntity
    {
        $model = $role->id()
            ? RoleModel::query()->findOrFail($role->id())
            : new RoleModel();

        $model->tenant_id = $role->tenantId();
        $model->name = $role->name();
        $model->slug = $role->slug();
        $model->description = $role->description();
        $model->save();

        return $this->toEntity($model->load('permissions'));
    }

    public function assignPermission(int $roleId, int $permissionId): void
    {
        RoleModel::query()->findOrFail($roleId)->permissions()->syncWithoutDetaching([$permissionId]);
    }

    public function revokePermission(int $roleId, int $permissionId): void
    {
        RoleModel::query()->findOrFail($roleId)->permissions()->detach($permissionId);
    }

    private function toEntity(RoleModel $model): RoleEntity
    {
        $permissions = $model->permissions
            ->map(fn ($permission) => new PermissionKey($permission->key))
            ->all();

        return new RoleEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            name: $model->name,
            slug: $model->slug,
            description: $model->description,
            permissions: $permissions,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
