<?php

namespace Tests\Feature\Nexus\Automation;

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
use App\Domains\Nexus\Catalog\Application\Actions\AddProductAction;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use Database\Seeders\NexusAutomationCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real Bearer tokens over POST /mcp/v1/execute — proves the manifest ->
 * Seeder -> CapabilityHandlerRegistry wiring, not just the Actions in
 * isolation (already covered by AutomationRuleActionsTest).
 */
class AutomationCapabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NexusAutomationCapabilitiesSeeder::class);
    }

    public function test_createInventoryAlert_thenList_thenPause_viaMcp(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $token = $this->tokenFor($business->id, ['nexus.automation.manage', 'nexus.automation.read']);

        $create = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.create_inventory_alert',
            'input' => ['product_id' => $product->id, 'threshold_quantity' => 2],
        ], ['Authorization' => "Bearer {$token}"]);
        $create->assertStatus(200);
        $ruleId = $create->json('data.rule.id');

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
        $list->assertStatus(200);
        $list->assertJsonCount(1, 'data.rules');

        $pause = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.pause',
            'input' => ['rule_id' => $ruleId],
        ], ['Authorization' => "Bearer {$token}"]);
        $pause->assertStatus(200);
        $pause->assertJsonPath('data.rule.status', 'paused');
    }

    public function test_createRecurringOrder_withoutPermission_isForbidden(): void
    {
        $buyer = $this->verifiedBusiness('Buyer Co');
        $seller = $this->verifiedBusiness('Seller Co');
        $token = $this->tokenFor($buyer->id, []);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.create_recurring_order',
            'input' => [
                'counterparty_business_id' => $seller->id,
                'catalog_item_type' => 'product',
                'catalog_item_id' => 1,
                'price_amount' => 10000,
                'price_currency' => 'IRT',
                'quantity' => 1,
                'interval_days' => 30,
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
    }

    public function test_deleteRule_viaMcp(): void
    {
        $business = $this->verifiedBusiness('Caller Co');
        $product = app(AddProductAction::class)->execute($business->id, 'محصول', 'Widget', 10_000, 'IRT', 5);
        $token = $this->tokenFor($business->id, ['nexus.automation.manage', 'nexus.automation.read']);

        $create = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.create_inventory_alert',
            'input' => ['product_id' => $product->id, 'threshold_quantity' => 2],
        ], ['Authorization' => "Bearer {$token}"]);
        $ruleId = $create->json('data.rule.id');

        $delete = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.delete',
            'input' => ['rule_id' => $ruleId],
        ], ['Authorization' => "Bearer {$token}"]);
        $delete->assertStatus(200);

        $list = $this->postJson('/mcp/v1/execute', [
            'capability' => 'nexus.automation.list',
            'input' => [],
        ], ['Authorization' => "Bearer {$token}"]);
        $list->assertJsonCount(0, 'data.rules');
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

        $role = app(CreateRoleAction::class)->execute($business->tenantId(), 'Automator', 'automator-'.uniqid());

        foreach ($permissionKeys as $permissionKey) {
            $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
            $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
            app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
        }

        app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $nexusAgent->coreAgentId(), $role->id);

        return app(GenerateAgentTokenAction::class)->execute($nexusAgent->coreAgentId())->plainToken;
    }
}
