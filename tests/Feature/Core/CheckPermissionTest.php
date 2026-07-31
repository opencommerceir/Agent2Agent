<?php

namespace Tests\Feature\Core;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CheckPermissionAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\Actions\RemoveMemberFromOrganizationAction;
use App\Core\Application\DTOs\AgentData;
use App\Core\Application\DTOs\OrganizationData;
use App\Core\Application\DTOs\TenantData;
use App\Core\Domain\ValueObjects\MemberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_whenAgentHasGrantedPermission_returnsTrue(): void
    {
        [$tenant, , $agent] = $this->makeTenantOrgAgent();

        $permission = app(CreatePermissionAction::class)->execute('commerce.products.read');
        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Reader', 'reader');
        app(AssignPermissionToRoleAction::class)->execute($role->id, $permission->id);
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $allowed = app(CheckPermissionAction::class)
            ->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.products.read');

        $this->assertTrue($allowed);
    }

    public function test_execute_whenAgentHasNoRoleAtAll_returnsFalse(): void
    {
        [$tenant, , $agent] = $this->makeTenantOrgAgent();

        $allowed = app(CheckPermissionAction::class)
            ->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.products.read');

        $this->assertFalse($allowed);
    }

    public function test_execute_whenAgentRoleLacksTheSpecificPermission_returnsFalse(): void
    {
        [$tenant, , $agent] = $this->makeTenantOrgAgent();

        $permission = app(CreatePermissionAction::class)->execute('commerce.orders.create');
        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Order Creator', 'order-creator');
        app(AssignPermissionToRoleAction::class)->execute($role->id, $permission->id);
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $allowed = app(CheckPermissionAction::class)
            ->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.orders.delete');

        $this->assertFalse($allowed);
    }

    /**
     * Regression guard for the event-driven cascade built in Phase 3
     * (RevokeRolesWhenMemberRemovedFromOrganization): removing a member from
     * their Organization must revoke every Core role they held, even though
     * nothing in this test calls RevokeRoleFromMemberAction directly.
     */
    public function test_execute_afterMemberRemovedFromOrganization_revokesPreviouslyGrantedPermission(): void
    {
        [$tenant, $organization, $agent] = $this->makeTenantOrgAgent();

        $permission = app(CreatePermissionAction::class)->execute('commerce.products.read');
        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Reader', 'reader');
        app(AssignPermissionToRoleAction::class)->execute($role->id, $permission->id);
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        $checkPermission = app(CheckPermissionAction::class);
        $this->assertTrue($checkPermission->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.products.read'));

        app(RemoveMemberFromOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $this->assertFalse($checkPermission->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.products.read'));
        $this->assertDatabaseMissing('member_roles', ['member_type' => 'agent', 'member_id' => $agent->id]);
    }

    /**
     * Regression guard for the N+1 fix in
     * EloquentMemberRoleRepository::findRolesForMember() (was 1 + 2N
     * queries for N roles — a findById() call per role id — now a
     * constant 1 + 2 regardless of N, via RoleRepositoryInterface::findByIds()).
     * Asserts the query count itself stays flat between 1 role and 5
     * roles, not just that the answer is still correct.
     */
    public function test_execute_queryCountStaysConstantRegardlessOfRoleCount(): void
    {
        [$tenant, , $agent] = $this->makeTenantOrgAgent();

        $permission = app(CreatePermissionAction::class)->execute('commerce.products.read');
        $roleOne = app(CreateRoleAction::class)->execute($tenant->id, 'Role One', 'role-one-'.uniqid());
        app(AssignPermissionToRoleAction::class)->execute($roleOne->id, $permission->id);
        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $roleOne->id);

        $checkPermission = app(CheckPermissionAction::class);

        DB::enableQueryLog();
        $this->assertTrue($checkPermission->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.products.read'));
        $queryCountForOneRole = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        for ($i = 2; $i <= 5; $i++) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, "Role {$i}", 'role-'.$i.'-'.uniqid());
            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        DB::enableQueryLog();
        $this->assertTrue($checkPermission->execute(MemberType::Agent, $agent->id, $tenant->id, 'commerce.products.read'));
        $queryCountForFiveRoles = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($queryCountForOneRole, $queryCountForFiveRoles);
    }

    /**
     * @return array{0: TenantData, 1: OrganizationData, 2: AgentData}
     */
    private function makeTenantOrgAgent(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-inc-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        return [$tenant, $organization, $agent];
    }
}
