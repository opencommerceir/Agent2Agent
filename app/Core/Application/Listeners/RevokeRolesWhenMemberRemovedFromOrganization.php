<?php

namespace App\Core\Application\Listeners;

use App\Core\Application\Actions\RevokeRoleFromMemberAction;
use App\Core\Domain\Events\MemberRemovedFromOrganization;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;

/**
 * Not requested explicitly, but required to satisfy "remove a member from
 * an Organization -> they lose all Core permissions" (the test scenario in
 * the brief): Role/Permission (this module) is tenant-scoped, not
 * organization-scoped, per the ERD you gave (Role attaches to Member, not
 * to Organization). So there is no structural link that makes a role
 * assignment expire on its own when org membership ends — it has to be an
 * explicit reaction.
 *
 * Runs synchronously (no ShouldQueue): revoking access is security-
 * sensitive and must take effect immediately, not after a queue delay.
 *
 * If a member is ever expected to belong to multiple organizations within
 * the same tenant while keeping org-specific roles, Role will need its own
 * organization_id scope — flagging that as a real constraint of today's
 * design, not something silently papered over.
 */
final class RevokeRolesWhenMemberRemovedFromOrganization
{
    public function __construct(
        private readonly MemberRoleRepositoryInterface $memberRoles,
        private readonly RevokeRoleFromMemberAction $revokeRole,
    ) {
    }

    public function handle(MemberRemovedFromOrganization $event): void
    {
        $member = $event->member;

        $assignments = $this->memberRoles->findAssignmentsForMember($member->memberType(), $member->memberId());

        foreach ($assignments as $assignment) {
            $this->revokeRole->execute($member->memberType(), $member->memberId(), $assignment->roleId());
        }
    }
}
