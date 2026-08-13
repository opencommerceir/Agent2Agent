<?php

namespace Tests\Feature\Nexus\Marketplace;

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
use Database\Seeders\NexusMarketplaceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real Bearer tokens over POST /mcp/v1/execute for the four AI
 * Recommendations capabilities (Phase 8/M3) — proves the manifest -> Seeder
 * -> CapabilityHandlerRegistry wiring, not just the Actions in isolation
 * (already covered by GetRecommendationsActionTest/RankSuppliersActionTest/
 * RecommendAlternativeSuppliersActionTest/RecommendNegotiationTimingActionTest).
 */
class MarketplaceRecommendationsCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusMarketplaceCapabilitiesSeeder::class);
    }

    public function test_recommendations_viaMcp_returnsSameIndustryBusinesses(): void
    {
        $caller = $this->verifiedBusiness('Caller Co', 'technology');
        $sameIndustry = $this->verifiedBusiness('Same Industry Co', 'technology');
        $token = $this->tokenFor($caller->id, ['nexus.marketplace.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.recommendations',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $businessIds = array_column($response->json('data.listings'), 'businessId');
        $this->assertContains($sameIndustry->id, $businessIds);
    }

    public function test_rankSuppliers_viaMcp_isFree(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $supplier = $this->verifiedBusiness('Supplier Co');
        $token = $this->tokenFor($caller->id, ['nexus.marketplace.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.rank_suppliers',
            'input' => ['business_ids' => [$supplier->id]],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.listings.0.businessId', $supplier->id);
    }

    public function test_alternatives_viaMcp_excludesTarget(): void
    {
        $caller = $this->verifiedBusiness('Caller Co', 'technology');
        $target = $this->verifiedBusiness('Target Co', 'technology');
        $alternative = $this->verifiedBusiness('Alternative Co', 'technology');
        $token = $this->tokenFor($caller->id, ['nexus.marketplace.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.alternatives',
            'input' => ['target_business_id' => $target->id],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $businessIds = array_column($response->json('data.listings'), 'businessId');
        $this->assertContains($alternative->id, $businessIds);
        $this->assertNotContains($target->id, $businessIds);
    }

    public function test_negotiationTiming_viaMcp_returnsSampleSize(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $counterparty = $this->verifiedBusiness('Counterparty Co');
        $token = $this->tokenFor($caller->id, ['nexus.marketplace.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.negotiation_timing',
            'input' => ['counterparty_business_id' => $counterparty->id],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.sampleSize', 0);
    }

    public function test_recommendations_withoutPermission_isForbidden(): void
    {
        $caller = $this->verifiedBusiness('Caller Co');
        $token = $this->tokenFor($caller->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.recommendations',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    private function verifiedBusiness(string $nameEn, string $industry = 'technology'): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::from($industry));
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
