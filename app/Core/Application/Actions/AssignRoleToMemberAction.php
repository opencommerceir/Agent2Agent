<?php

namespace App\Core\Application\Actions;

use App\Core\Domain\Entities\MemberRole;
use App\Core\Domain\Events\RoleAssignedToMember;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;
use App\Core\Domain\Repositories\RoleRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class AssignRoleToMemberAction
{
    public function __construct(
        private readonly MemberRoleRepositoryInterface $memberRoles,
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    public function execute(MemberType $memberType, int $memberId, int $roleId): void
    {
        if (! $this->roles->findById($roleId)) {
            throw new InvalidArgumentException("Role [{$roleId}] does not exist.");
        }

        if ($this->memberRoles->findAssignment($memberType, $memberId, $roleId)) {
            return; // already assigned — idempotent
        }

        $assignment = MemberRole::assign($memberType, $memberId, $roleId);
        $assignment = $this->memberRoles->save($assignment);

        Event::dispatch(new RoleAssignedToMember($assignment));
    }
}
