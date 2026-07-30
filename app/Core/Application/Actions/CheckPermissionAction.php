<?php

namespace App\Core\Application\Actions;

use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Core\Domain\Repositories\MemberRoleRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;

/**
 * The single place in Core that answers "can this member do X?" — the
 * only permission logic the future MCP Gateway and Domain Modules should
 * ever call, rather than querying roles/permissions themselves.
 *
 * execute() is the primary query (returns bool, matches every other
 * Action's naming convention); authorize() is a thin convenience wrapper
 * for call sites that want a throw-on-deny assertion instead of an if.
 */
final class CheckPermissionAction
{
    public function __construct(
        private readonly MemberRoleRepositoryInterface $memberRoles,
    ) {
    }

    public function execute(MemberType $memberType, int $memberId, int $tenantId, string $permissionKey): bool
    {
        $key = new PermissionKey($permissionKey);

        foreach ($this->memberRoles->findRolesForMember($memberType, $memberId, $tenantId) as $role) {
            if ($role->hasPermission($key)) {
                return true;
            }
        }

        return false;
    }

    public function authorize(MemberType $memberType, int $memberId, int $tenantId, string $permissionKey): void
    {
        if (! $this->execute($memberType, $memberId, $tenantId, $permissionKey)) {
            throw new PermissionDeniedException(
                "Permission [{$permissionKey}] denied for {$memberType->value} #{$memberId}."
            );
        }
    }
}
