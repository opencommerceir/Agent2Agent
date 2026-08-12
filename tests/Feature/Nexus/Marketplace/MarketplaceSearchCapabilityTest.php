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
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use Database\Seeders\NexusMarketplaceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the whole MCP chain for a Nexus capability end-to-end: a real
 * Core Agent+Bearer token (provisioned by CreateAgentForBusinessAction's
 * event-driven flow, same as production) calling POST /mcp/v1/execute
 * and getting real cross-tenant Marketplace results back — not just that
 * SearchMarketplaceAction works in isolation (already covered).
 */
class MarketplaceSearchCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusMarketplaceCapabilitiesSeeder::class);
    }

    public function test_search_viaMcp_returnsOtherVerifiedBusinesses(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);

        $supplier = app(RegisterBusinessAction::class)->execute('تأمین‌کننده', 'Supplier Co', BusinessType::Company, Industry::Retail);
        app(VerifyBusinessAction::class)->execute($supplier->id);
        app(AddProductAction::class)->execute($supplier->id, 'لپ‌تاپ', 'Laptop', 5000000, 'IRT', 10);

        $token = $this->tokenFor($caller->id, ['nexus.marketplace.read']);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.search',
            'input' => ['query' => 'Laptop'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.listings.0.businessId', $supplier->id);
        $response->assertJsonPath('data.listings.0.products.0.nameEn', 'Laptop');
    }

    public function test_search_viaMcp_withoutPermission_isForbidden(): void
    {
        $caller = app(RegisterBusinessAction::class)->execute('من', 'Caller Co', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($caller->id);

        $token = $this->tokenFor($caller->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.marketplace.search',
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
