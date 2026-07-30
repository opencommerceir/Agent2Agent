<?php

namespace App\Core\Application\Actions;

use App\Core\Application\DTOs\OrganizationMemberData;
use App\Core\Domain\Entities\OrganizationMember;
use App\Core\Domain\Events\MemberAddedToOrganization;
use App\Core\Domain\Repositories\OrganizationMemberRepositoryInterface;
use App\Core\Domain\Repositories\OrganizationRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\OrganizationMemberRole;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Does not verify that the User/Agent identified by $memberId actually
 * exists — Agent existence could be checked via AgentRepositoryInterface,
 * but there is no equivalent User repository yet in Core, and validating
 * only one of the two polymorphic member types would be inconsistent.
 * The organizations/agents/users foreign keys still protect referential
 * integrity in the meantime.
 */
final class AddMemberToOrganizationAction
{
    public function __construct(
        private readonly OrganizationMemberRepositoryInterface $members,
        private readonly OrganizationRepositoryInterface $organizations,
    ) {
    }

    public function execute(
        int $organizationId,
        MemberType $memberType,
        int $memberId,
        OrganizationMemberRole $roleInOrg = OrganizationMemberRole::Member,
    ): OrganizationMemberData {
        $organization = $this->organizations->findById($organizationId);

        if (! $organization) {
            throw new InvalidArgumentException("Organization [{$organizationId}] does not exist.");
        }

        if ($this->members->findMembership($organizationId, $memberType, $memberId)) {
            throw new InvalidArgumentException('This member already belongs to the organization.');
        }

        $member = OrganizationMember::add(
            tenantId: $organization->tenantId(),
            organizationId: $organizationId,
            memberType: $memberType,
            memberId: $memberId,
            roleInOrg: $roleInOrg,
        );

        $member = $this->members->save($member);

        Event::dispatch(new MemberAddedToOrganization($member));

        return OrganizationMemberData::fromEntity($member);
    }
}
