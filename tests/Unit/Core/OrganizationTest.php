<?php

namespace Tests\Unit\Core;

use App\Core\Domain\Entities\Organization;
use App\Core\Domain\Entities\OrganizationMember;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\OrganizationMemberRole;
use App\Core\Domain\ValueObjects\OrganizationStatus;
use PHPUnit\Framework\TestCase;

class OrganizationTest extends TestCase
{
    public function test_create_withValidData_createsActiveOrganizationWithNoOwner(): void
    {
        $organization = Organization::create(1, 'Acme Store', 'acme-store');

        $this->assertSame('Acme Store', $organization->name());
        $this->assertSame(OrganizationStatus::Active, $organization->status());
        $this->assertNull($organization->ownerUserId());
    }

    public function test_assignOwner_setsOwnerUserId(): void
    {
        $organization = Organization::create(1, 'Acme Store', 'acme-store');

        $organization->assignOwner(42);

        $this->assertSame(42, $organization->ownerUserId());
    }

    public function test_suspend_setsStatusToSuspendedAndNotActive(): void
    {
        $organization = Organization::create(1, 'Acme Store', 'acme-store');

        $organization->suspend();

        $this->assertSame(OrganizationStatus::Suspended, $organization->status());
        $this->assertFalse($organization->isActive());
    }

    public function test_organizationMemberAdd_withDefaults_createsMembershipWithMemberRole(): void
    {
        $member = OrganizationMember::add(
            tenantId: 1,
            organizationId: 10,
            memberType: MemberType::Agent,
            memberId: 99,
        );

        $this->assertSame(MemberType::Agent, $member->memberType());
        $this->assertSame(99, $member->memberId());
        $this->assertSame(OrganizationMemberRole::Member, $member->roleInOrg());
    }

    public function test_organizationMemberChangeRoleInOrg_updatesRole(): void
    {
        $member = OrganizationMember::add(1, 10, MemberType::Agent, 99);

        $member->changeRoleInOrg(OrganizationMemberRole::Admin);

        $this->assertSame(OrganizationMemberRole::Admin, $member->roleInOrg());
    }
}
