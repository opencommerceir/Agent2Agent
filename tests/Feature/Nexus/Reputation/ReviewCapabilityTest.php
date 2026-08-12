<?php

namespace Tests\Feature\Nexus\Reputation;

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
use App\Domains\Nexus\Contract\Application\Actions\ReleaseEscrowAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Negotiation\Application\Actions\AcceptDealAction;
use App\Domains\Nexus\Negotiation\Application\Actions\InitiateNegotiationAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;
use Database\Seeders\NexusReputationCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real Bearer tokens over POST /mcp/v1/execute, same rigor
 * NegotiationCapabilityTest already applies — proves the manifest→Seeder→
 * CapabilityHandlerRegistry wiring, not just the Action in isolation
 * (already covered by SubmitReviewActionTest).
 */
class ReviewCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusReputationCapabilitiesSeeder::class);
    }

    public function test_submitAndListReviews_viaMcp(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);

        $buyerToken = $this->tokenFor($buyer->id, ['nexus.reputation.manage', 'nexus.reputation.read']);
        $sellerToken = $this->tokenFor($seller->id, ['nexus.reputation.read']);

        $submit = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.review.submit',
            'input' => ['negotiation_id' => $negotiation->id, 'rating' => 5, 'comment' => 'great partner'],
        ], ['Authorization' => "Bearer {$buyerToken}"]);
        $submit->assertStatus(200);
        $submit->assertJsonPath('data.review.rating', 5);
        $submit->assertJsonPath('data.review.revieweeBusinessId', $seller->id);

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.review.list',
            'input' => ['business_id' => $seller->id],
        ], ['Authorization' => "Bearer {$sellerToken}"]);
        $list->assertStatus(200);
        $list->assertJsonCount(1, 'data.reviews');
        $list->assertJsonPath('data.reviews.0.rating', 5);
    }

    public function test_submit_withoutPermission_isForbidden(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $negotiation = app(InitiateNegotiationAction::class)->execute(
            $buyer->id, $seller->id, CatalogItemType::Product, 1,
            new NegotiationTerms(Money::fromAmount(1_000_000, 'IRT'), 1, null),
        );
        app(AcceptDealAction::class)->execute($negotiation->id, $buyer->id);
        app(ReleaseEscrowAction::class)->execute($negotiation->id, $buyer->id);

        $buyerToken = $this->tokenFor($buyer->id, []);

        $submit = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.review.submit',
            'input' => ['negotiation_id' => $negotiation->id, 'rating' => 5, 'comment' => null],
        ], ['Authorization' => "Bearer {$buyerToken}"]);

        $submit->assertStatus(403);
    }

    public function test_reputationScore_viaMcp_returnsComputedScore(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $buyerToken = $this->tokenFor($buyer->id, ['nexus.reputation.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.reputation.score',
            'input' => ['business_id' => $seller->id],
        ], ['Authorization' => "Bearer {$buyerToken}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.score.businessId', $seller->id);
        $response->assertJsonPath('data.score.badges', ['verified']);
    }

    private function verifiedBusiness(string $nameEn): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
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

        $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Reviewer', 'reviewer-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $nexusAgent->coreAgentId(), $role->id);

        return app(GenerateAgentTokenAction::class)->execute($nexusAgent->coreAgentId())->plainToken;
    }
}
