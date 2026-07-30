<?php

namespace App\Core\Application\Actions;

use App\Core\Domain\Events\RoleRevokedFromMember;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Support\Facades\Event;

final class RevokeRoleFromMemberAction
{
    public function __construct(
        private readonly MemberRoleRepositoryInterface $memberRoles,
    ) {
    }

    public function execute(MemberType $memberType, int $memberId, int $roleId): void
    {
        $assignment = $this->memberRoles->findAssignment($memberType, $memberId, $roleId);

        if (! $assignment) {
            return; // already not assigned — idempotent
        }

        $this->memberRoles->delete($assignment);

        Event::dispatch(new RoleRevokedFromMember($assignment));
    }
}
