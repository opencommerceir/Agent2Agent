<?php

namespace Tests\Feature\Nexus\Analytics;

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
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusAnalyticsCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictiveIntelligenceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusAnalyticsCapabilitiesSeeder::class);
    }

    public function test_forecast_viaMcp_isFree(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $target = $this->verifiedBusiness('Target Co');
        $token = $this->tokenFor($caller->id, ['nexus.analytics.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.analytics.forecast',
            'input' => ['business_id' => $target->id],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.businessId', $target->id);
    }

    public function test_risk_viaMcp(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $target = $this->verifiedBusiness('Target Co');
        $token = $this->tokenFor($caller->id, ['nexus.analytics.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.analytics.risk',
            'input' => ['counterparty_business_id' => $target->id, 'deal_amount' => 10000, 'currency' => 'IRT'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.riskLevel', 'medium'); // fresh business, zero reputation -> 50/100
    }

    public function test_scenario_viaMcp(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $target = $this->verifiedBusiness('Target Co');
        $token = $this->tokenFor($caller->id, ['nexus.analytics.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.analytics.scenario',
            'input' => ['counterparty_business_id' => $target->id, 'catalog_item_type' => 'product', 'hypothetical_unit_amount' => 10000],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.estimatedAcceptanceLikelihood', null);
    }

    public function test_forecast_withoutPermission_isForbidden(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $token = $this->tokenFor($caller->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.analytics.forecast',
            'input' => ['business_id' => $caller->id],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        app(GrantCreditsAction::class)->execute($business->id, 100_000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function tokenFor(int $businessId, array $permissionKeys): string
    {
        $business = app(BusinessRepositoryInterface::class)->findById($businessId);
        $nexusAgent = app(AgentRepositoryInterface::class)->findByBusinessId($businessId);

        $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Analyst', 'analyst-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $nexusAgent->coreAgentId(), $role->id);

        return app(GenerateAgentTokenAction::class)->execute($nexusAgent->coreAgentId())->plainToken;
    }
}
