<?php

namespace Tests\Feature\AgentOrchestrator;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Application\DTOs\AuthContext;
use App\Core\Domain\Exceptions\PermissionDeniedException;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\AgentOrchestrator\Application\Services\CapabilityToolInvoker;
use App\Modules\AgentOrchestrator\Domain\Exceptions\CapabilityNotFoundException;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises CapabilityToolInvoker against the *real* Capability Registry,
 * CheckPermissionAction, and CapabilityExecutionService — the same
 * building blocks AbstractMCPGatewayController itself uses — confirming
 * this module's own "no second execution path" requirement rather than
 * asserting against a mock of Core's own machinery.
 */
class CapabilityToolInvokerTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoke_runsARealCapabilityThroughTheRealMcpMachinery(): void
    {
        [$tenantId, $agentId] = $this->registerAgentWithPermissions(['commerce.coupons.create']);

        $invoker = app(CapabilityToolInvoker::class);

        $result = $invoker->invoke(
            'commerce.coupon.create',
            ['code' => 'COUPON-ABCDE', 'discount_type' => 'percentage', 'discount_value' => 10],
            new AuthContext(tenantId: $tenantId, agentId: $agentId),
        );

        $this->assertSame('COUPON-ABCDE', $result['coupon']['code']);
        $this->assertDatabaseHas('coupons', ['tenant_id' => $tenantId, 'code' => 'COUPON-ABCDE']);
    }

    public function test_invoke_wrapsAnUnknownCapabilityInThisModulesOwnException(): void
    {
        [$tenantId, $agentId] = $this->registerAgentWithPermissions([]);

        $invoker = app(CapabilityToolInvoker::class);

        $this->expectException(CapabilityNotFoundException::class);

        $invoker->invoke('commerce.does_not.exist', [], new AuthContext(tenantId: $tenantId, agentId: $agentId));
    }

    public function test_invoke_rejectsAnAgentMissingTheRequiredPermission(): void
    {
        [$tenantId, $agentId] = $this->registerAgentWithPermissions([]); // no permissions granted

        $invoker = app(CapabilityToolInvoker::class);

        $this->expectException(PermissionDeniedException::class);

        $invoker->invoke(
            'commerce.coupon.create',
            ['code' => 'COUPON-ABCDE', 'discount_type' => 'percentage', 'discount_value' => 10],
            new AuthContext(tenantId: $tenantId, agentId: $agentId),
        );
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);

        return [$tenant->id, $agent->id];
    }
}
