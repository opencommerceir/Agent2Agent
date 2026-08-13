<?php

namespace Tests\Feature\Nexus\PrivateMarketplace;

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
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\AcceptMemberInvitationAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\CreatePrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\GetPrivateMarketplaceAction;
use App\Domains\Nexus\PrivateMarketplace\Application\Actions\InviteMemberAction;
use Database\Seeders\NexusPrivateMarketplaceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateMarketplaceCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusPrivateMarketplaceCapabilitiesSeeder::class);
    }

    public function test_listThenSearch_viaMcp_memberSeesConfidentialPrice(): void
    {
        $owner = $this->verifiedBusiness('Owner Co', 100);
        $member = $this->verifiedBusiness('Member Co', 100);
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market');
        $this->joinAsActiveMember($marketplace->id, $owner->id, $member->id);

        $ownerToken = $this->tokenFor($owner->id, ['nexus.marketplace.read']);
        $memberToken = $this->tokenFor($member->id, ['nexus.marketplace.read']);

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.private_marketplace.list_listing',
            'input' => [
                'marketplace_id' => $marketplace->id,
                'catalog_item_type' => 'product',
                'catalog_item_id' => 7,
                'special_price_amount' => 42000,
                'special_price_currency' => 'IRT',
            ],
        ], ['Authorization' => "Bearer {$ownerToken}"]);
        $list->assertStatus(200);

        $search = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.private_marketplace.search',
            'input' => ['marketplace_id' => $marketplace->id],
        ], ['Authorization' => "Bearer {$memberToken}"]);
        $search->assertStatus(200);
        $this->assertCount(1, $search->json('data.listings'));
        $this->assertSame(42000, $search->json('data.listings.0.specialPriceAmount'));
    }

    public function test_search_viaMcp_withoutPermission_isForbidden(): void
    {
        $owner = $this->verifiedBusiness('Owner Co', 100);
        $marketplace = app(CreatePrivateMarketplaceAction::class)->execute($owner->id, 'الف', 'Alpha Market');
        $token = $this->tokenFor($owner->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.private_marketplace.search',
            'input' => ['marketplace_id' => $marketplace->id],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    private function joinAsActiveMember(int $marketplaceId, int $ownerId, int $memberBusinessId): void
    {
        app(InviteMemberAction::class)->execute($marketplaceId, $ownerId, $memberBusinessId);
        $data = app(GetPrivateMarketplaceAction::class)->execute($marketplaceId);
        $memberRow = collect($data->members)->firstWhere('businessId', $memberBusinessId);
        app(AcceptMemberInvitationAction::class)->execute($memberRow['id'], $memberBusinessId);
    }

    private function verifiedBusiness(string $nameEn, int $credits = 0): BusinessData
    {
        $business = app(RegisterBusinessAction::class)->execute("نام {$nameEn}", $nameEn, BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        if ($credits > 0) {
            app(GrantCreditsAction::class)->execute($business->id, $credits, CreditTransactionType::AdminGrant, 'test.seed');
        }

        return $business;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    private function tokenFor(int $businessId, array $permissionKeys): string
    {
        $business = app(BusinessRepositoryInterface::class)->findById($businessId);
        $nexusAgent = app(AgentRepositoryInterface::class)->findByBusinessId($businessId);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Trader', 'trader-'.uniqid());

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
