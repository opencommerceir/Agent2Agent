<?php

namespace App\Core\Infrastructure\Repositories;

use App\Core\Domain\Entities\OrganizationMember as OrganizationMemberEntity;
use App\Core\Domain\Repositories\OrganizationMemberRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\OrganizationMemberRole;
use App\Core\Infrastructure\Models\OrganizationMember as OrganizationMemberModel;
use DateTimeImmutable;

class EloquentOrganizationMemberRepository implements OrganizationMemberRepositoryInterface
{
    public function findMembership(int $organizationId, MemberType $memberType, int $memberId): ?OrganizationMemberEntity
    {
        $model = OrganizationMemberModel::query()
            ->where('organization_id', $organizationId)
            ->where('member_type', $memberType->value)
            ->where('member_id', $memberId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(OrganizationMemberEntity $member): OrganizationMemberEntity
    {
        $model = $member->id()
            ? OrganizationMemberModel::query()->findOrFail($member->id())
            : new OrganizationMemberModel();

        $model->tenant_id = $member->tenantId();
        $model->organization_id = $member->organizationId();
        $model->member_type = $member->memberType()->value;
        $model->member_id = $member->memberId();
        $model->role_in_org = $member->roleInOrg()->value;
        $model->save();

        return $this->toEntity($model);
    }

    public function delete(OrganizationMemberEntity $member): void
    {
        OrganizationMemberModel::query()->where('id', $member->id())->delete();
    }

    private function toEntity(OrganizationMemberModel $model): OrganizationMemberEntity
    {
        return new OrganizationMemberEntity(
            id: $model->id,
            tenantId: $model->tenant_id,
            organizationId: $model->organization_id,
            memberType: MemberType::from($model->member_type),
            memberId: $model->member_id,
            roleInOrg: OrganizationMemberRole::from($model->role_in_org),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
