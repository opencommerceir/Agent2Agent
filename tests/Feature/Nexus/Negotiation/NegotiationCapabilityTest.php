<?php

namespace Tests\Feature\Nexus\Negotiation;

use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Domains\Nexus\Agent\Application\Actions\SetAuthorityLimitsAction;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusNegotiationCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two real Agents (different Tenants, different Businesses) negotiating
 * entirely over POST /mcp/v1/execute — the concrete proof the cross-tenant
 * design (Negotiation entity's own docblock) works through the real MCP
 * Gateway, not just at the Action level (already covered by
 * NegotiationActionsTest).
 */
class NegotiationCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusNegotiationCapabilitiesSeeder::class);
    }

    public function test_fullNegotiationFlow_viaMcp_proposeCounterAcceptStatus(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerToken = $this->tokenFor($buyer->id, ['nexus.negotiation.manage', 'nexus.negotiation.read']);
        $sellerToken = $this->tokenFor($seller->id, ['nexus.negotiation.manage', 'nexus.negotiation.read']);

        $propose = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.propose',
            'input' => [
                'counterparty_business_id' => $seller->id,
                'catalog_item_type' => 'product',
                'catalog_item_id' => 1,
                'price_amount' => 100000,
                'price_currency' => 'IRT',
            ],
        ], ['Authorization' => "Bearer {$buyerToken}"]);
        $propose->assertStatus(200);
        $propose->assertJsonPath('data.negotiation.status', 'proposed');
        $negotiationId = $propose->json('data.negotiation.id');

        $counter = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.counter',
            'input' => ['negotiation_id' => $negotiationId, 'price_amount' => 90000, 'price_currency' => 'IRT'],
        ], ['Authorization' => "Bearer {$sellerToken}"]);
        $counter->assertStatus(200);
        $counter->assertJsonPath('data.negotiation.status', 'countered');
        $counter->assertJsonPath('data.negotiation.currentTerms.priceAmount', 90000);

        $accept = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.accept',
            'input' => ['negotiation_id' => $negotiationId],
        ], ['Authorization' => "Bearer {$buyerToken}"]);
        $accept->assertStatus(200);
        $accept->assertJsonPath('data.negotiation.status', 'accepted');

        $status = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.status',
            'input' => ['negotiation_id' => $negotiationId],
        ], ['Authorization' => "Bearer {$sellerToken}"]);
        $status->assertStatus(200);
        $status->assertJsonPath('data.negotiation.status', 'accepted');
    }

    public function test_reject_viaMcp_setsRejectedStatus(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerToken = $this->tokenFor($buyer->id, ['nexus.negotiation.manage']);
        $sellerToken = $this->tokenFor($seller->id, ['nexus.negotiation.manage']);

        $propose = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.propose',
            'input' => ['counterparty_business_id' => $seller->id, 'catalog_item_type' => 'product', 'catalog_item_id' => 1, 'price_amount' => 100000, 'price_currency' => 'IRT'],
        ], ['Authorization' => "Bearer {$buyerToken}"]);
        $negotiationId = $propose->json('data.negotiation.id');

        $reject = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.reject',
            'input' => ['negotiation_id' => $negotiationId, 'reason' => 'not interested'],
        ], ['Authorization' => "Bearer {$sellerToken}"]);

        $reject->assertStatus(200);
        $reject->assertJsonPath('data.negotiation.status', 'rejected');
    }

    public function test_accept_beyondAuthorityLimit_viaMcp_pausesForHumanApproval(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerAgent = app(AgentRepositoryInterface::class)->findByBusinessId($buyer->id);
        app(SetAuthorityLimitsAction::class)->execute($buyerAgent->id(), ['max_deal_value' => 50000]);
        $buyerToken = $this->tokenFor($buyer->id, ['nexus.negotiation.manage']);
        $sellerToken = $this->tokenFor($seller->id, ['nexus.negotiation.manage']);

        $propose = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.propose',
            'input' => ['counterparty_business_id' => $buyer->id, 'catalog_item_type' => 'product', 'catalog_item_id' => 1, 'price_amount' => 100000, 'price_currency' => 'IRT'],
        ], ['Authorization' => "Bearer {$sellerToken}"]);
        $propose->assertStatus(200);
        $negotiationId = $propose->json('data.negotiation.id');

        $accept = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.negotiation.accept',
            'input' => ['negotiation_id' => $negotiationId],
        ], ['Authorization' => "Bearer {$buyerToken}"]);

        $accept->assertStatus(200);
        $accept->assertJsonPath('data.negotiation.status', 'pending_approval');
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        // Phase 3/M2's CostGate now gates propose/counter/accept/reject —
        // a generous flat top-up so this domain's own tests keep exercising
        // negotiation mechanics, not credit exhaustion.
        app(GrantCreditsAction::class)->execute($business->id, 100000, CreditTransactionType::AdminGrant, 'test.seed');

        return $business;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function tokenFor(int $businessId, array $permissionKeys): string
    {
        $business = app(BusinessRepositoryInterface::class)->findById($businessId);
        $nexusAgent = app(AgentRepositoryInterface::class)->findByBusinessId($businessId);

        $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Negotiator', 'negotiator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $nexusAgent->coreAgentId(), $role->id);

        return app(GenerateAgentTokenAction::class)->execute($nexusAgent->coreAgentId())->plainToken;
    }
}
