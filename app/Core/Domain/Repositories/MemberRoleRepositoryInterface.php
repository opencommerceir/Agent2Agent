<?php

namespace App\Core\Domain\Repositories;

use App\Core\Domain\Entities\MemberRole;
use App\Core\Domain\Entities\Role;
use App\Core\Domain\ValueObjects\MemberType;

interface MemberRoleRepositoryInterface
{
    public function findAssignment(MemberType $memberType, int $memberId, int $roleId): ?MemberRole;

    /**
     * @return list<MemberRole>
     */
    public function findAssignmentsForMember(MemberType $memberType, int $memberId): array;

    /**
     * Resolves straight to fully-loaded Role entities (permissions included)
     * scoped to $tenantId — this is what CheckPermissionAction walks.
     * Filtering by tenant here is a data-scoping query, not an
     * authorization decision, since it only narrows *which* roles are
     * fetched for an already-identified member.
     *
     * @return list<Role>
     */
    public function findRolesForMember(MemberType $memberType, int $memberId, int $tenantId): array;

    public function save(MemberRole $memberRole): MemberRole;

    public function delete(MemberRole $memberRole): void;
}
