<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\MemberRole as MemberRoleEntity;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;
use App\Core\Domain\Repositories\RoleRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Infrastructure\Models\MemberRole as MemberRoleModel;
use DateTimeImmutable;

class EloquentMemberRoleRepository implements MemberRoleRepositoryInterface
{
    /**
     * Depends on RoleRepositoryInterface (not the Role Eloquent model
     * directly) to reuse its Role-entity mapping instead of duplicating
     * the permissions-hydration logic here. Two Infrastructure classes
     * depending on each other is fine — DDD's "no framework in Domain"
     * rule doesn't apply between Infrastructure siblings.
     */
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    public function findAssignment(MemberType $memberType, int $memberId, int $roleId): ?MemberRoleEntity
    {
        $model = MemberRoleModel::query()
            ->where('member_type', $memberType->value)
            ->where('member_id', $memberId)
            ->where('role_id', $roleId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findAssignmentsForMember(MemberType $memberType, int $memberId): array
    {
        return MemberRoleModel::query()
            ->where('member_type', $memberType->value)
            ->where('member_id', $memberId)
            ->get()
            ->map(fn ($model) => $this->toEntity($model))
            ->all();
    }

    public function findRolesForMember(MemberType $memberType, int $memberId, int $tenantId): array
    {
        // N+1 by design for now — role counts per member are small.
        // Revisit with a dedicated projection/cache once CheckPermission
        // is on the hot path (see the "cache-friendly in the future" note).
        $roleIds = MemberRoleModel::query()
            ->where('member_type', $memberType->value)
            ->where('member_id', $memberId)
            ->whereHas('role', fn ($query) => $query->where('tenant_id', $tenantId))
            ->pluck('role_id');

        return $roleIds
            ->map(fn (int $roleId) => $this->roles->findById($roleId))
            ->filter()
            ->values()
            ->all();
    }

    public function save(MemberRoleEntity $memberRole): MemberRoleEntity
    {
        $model = $memberRole->id()
            ? MemberRoleModel::query()->findOrFail($memberRole->id())
            : new MemberRoleModel();

        $model->member_type = $memberRole->memberType()->value;
        $model->member_id = $memberRole->memberId();
        $model->role_id = $memberRole->roleId();
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(MemberRoleEntity $memberRole): void
    {
        MemberRoleModel::query()->where('id', $memberRole->id())->delete();
    }

    private function toEntity(MemberRoleModel $model): MemberRoleEntity
    {
        return new MemberRoleEntity(
            id: $model->id,
            memberType: MemberType::from($model->member_type),
            memberId: $model->member_id,
            roleId: $model->role_id,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
