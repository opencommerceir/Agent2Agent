<?php

namespace Tests\Feature\Nexus\Growth;

use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use Database\Seeders\NexusGrowthCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves nexus.referral.status end-to-end over the real MCP Gateway,
 * and that (like nexus.credit.balance) checking your own standing is free —
 * no credit deduction, regardless of balance.
 */
class ReferralCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusGrowthCapabilitiesSeeder::class);
    }

    public function test_referralStatus_viaMcp_returnsCodeAndCounts(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        $token = $this->tokenFor($caller->id, ['nexus.growth.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.referral.status',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.tier1Count', 0);
        $this->assertNotNull($response->json('data.code'));
    }

    public function test_referralStatus_viaMcp_withoutPermission_isForbidden(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        $token = $this->tokenFor($caller->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.referral.status',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function tokenFor(int $businessId, array $permissionKeys): string
    {
        $business = app(BusinessRepositoryInterface::class)->findById($businessId);
        $nexusAgent = app(AgentRepositoryInterface::class)->findByBusinessId($businessId);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Negotiator', 'negotiator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $nexusAgent->coreAgentId(), $role->id);
        }

        return app(GenerateAgentTokenAction::class)->execute($nexusAgent->coreAgentId())->plainToken;
    }
}
