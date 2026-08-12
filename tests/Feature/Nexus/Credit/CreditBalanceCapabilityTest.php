<?php

namespace Tests\Feature\Nexus\Credit;

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
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusCreditCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the whole MCP chain for nexus.credit.balance end-to-end — a real
 * Core Agent+Bearer token, and that checking your own balance never costs
 * anything (CreditCapabilities' own manifest docblock).
 */
class CreditBalanceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusCreditCapabilitiesSeeder::class);
    }

    public function test_balance_viaMcp_returnsCurrentBalanceAndDoesNotChargeIt(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        app(GrantCreditsAction::class)->execute($caller->id, 42, CreditTransactionType::AdminGrant, 'test.seed');
        $token = $this->tokenFor($caller->id, ['nexus.credit.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.credit.balance',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.balance', 42);
        $response->assertJsonPath('data.businessId', $caller->id);
    }

    public function test_balance_viaMcp_withoutPermission_isForbidden(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);
        $token = $this->tokenFor($caller->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.credit.balance',
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
