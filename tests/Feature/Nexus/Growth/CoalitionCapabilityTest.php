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
use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusGrowthCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoalitionCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusGrowthCapabilitiesSeeder::class);
    }

    public function test_fullCoalitionFlow_viaMcp_createJoinListClose(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co', 100);
        $joiner = $this->verifiedBusiness('Joiner Co', 100);
        $organizerToken = $this->tokenFor($organizer->id, ['nexus.growth.manage', 'nexus.growth.read']);
        $joinerToken = $this->tokenFor($joiner->id, ['nexus.growth.manage', 'nexus.growth.read']);

        $create = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.coalition.create',
            'input' => [
                'target_business_id' => $target->id,
                'catalog_item_type' => 'product',
                'catalog_item_id' => 1,
                'unit_price_amount' => 10000,
                'unit_price_currency' => 'IRT',
                'min_participants' => 2,
                'discount_percent' => 10,
                'quantity' => 5,
            ],
        ], ['Authorization' => "Bearer {$organizerToken}"]);
        $create->assertStatus(200);
        $create->assertJsonPath('data.coalition.status', 'forming');
        $coalitionId = $create->json('data.coalition.id');

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.coalition.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$joinerToken}"]);
        $list->assertStatus(200);
        $this->assertCount(1, $list->json('data.coalitions'));

        $join = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.coalition.join',
            'input' => ['coalition_id' => $coalitionId, 'quantity' => 3],
        ], ['Authorization' => "Bearer {$joinerToken}"]);
        $join->assertStatus(200);
        $this->assertCount(2, $join->json('data.coalition.members'));

        $close = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.coalition.close',
            'input' => ['coalition_id' => $coalitionId],
        ], ['Authorization' => "Bearer {$organizerToken}"]);
        $close->assertStatus(200);
        $close->assertJsonPath('data.coalition.status', 'negotiating');
        $this->assertNotNull($close->json('data.coalition.negotiationId'));
    }

    public function test_coalitionCreate_viaMcp_withoutPermission_isForbidden(): void
    {
        $organizer = $this->verifiedBusiness('Organizer Co', 100);
        $target = $this->verifiedBusiness('Target Co');
        $token = $this->tokenFor($organizer->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.coalition.create',
            'input' => [
                'target_business_id' => $target->id,
                'catalog_item_type' => 'product',
                'catalog_item_id' => 1,
                'unit_price_amount' => 10000,
                'unit_price_currency' => 'IRT',
                'min_participants' => 2,
                'discount_percent' => 10,
                'quantity' => 5,
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
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
