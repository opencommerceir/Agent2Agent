<?php

namespace App\Core\Application\Actions;

use App\Core\Domain\Events\MemberRemovedFromOrganization;
use App\Core\Domain\Repositories\OrganizationMemberRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Deliberately does *not* also revoke the member's Core roles inline —
 * that would make this Action responsible for two operations (Application
 * Layer Rules: one Action = one responsibility). Role revocation on
 * removal is a side effect handled by the
 * RevokeRolesWhenMemberRemovedFromOrganization listener reacting to the
 * MemberRemovedFromOrganization event dispatched below (Event Driven
 * Communication, Decision 012 — same pattern as OrderCreated ->
 * InventoryUpdated in architecture.md).
 */
final class RemoveMemberFromOrganizationAction
{
    public function __construct(
        private readonly OrganizationMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $organizationId, MemberType $memberType, int $memberId): void
    {
        $membership = $this->members->findMembership($organizationId, $memberType, $memberId);

        if (! $membership) {
            throw new InvalidArgumentException('This member does not belong to the organization.');
        }

        $this->members->delete($membership);

        Event::dispatch(new MemberRemovedFromOrganization($membership));
    }
}
