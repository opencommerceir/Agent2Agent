<?php

namespace Tests\Feature\Loyalty;

use App\Core\Application\Actions\AddMemberToOrganizationAction;
use App\Core\Application\Actions\AssignPermissionToRoleAction;
use App\Core\Application\Actions\AssignRoleToMemberAction;
use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreatePermissionAction;
use App\Core\Application\Actions\CreateRoleAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\GenerateAgentTokenAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\Repositories\PermissionRepositoryInterface;
use App\Core\Domain\ValueObjects\MemberType;
use App\Core\Domain\ValueObjects\PermissionKey;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\LoyaltyCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full Phase 3.4 (Loyalty) scenario over real MCP HTTP requests plus
 * real Commerce Action calls: create a Customer -> open a LoyaltyAccount
 * -> place a real $150 Order for that Customer -> Commerce's real
 * OrderWasPlaced event fires -> OrderPlacedListener (registered in
 * LoyaltyServiceProvider::boot(), never called directly by this test)
 * reacts -> 150 points are earned automatically -> redeem a Reward ->
 * attempt an over-budget redemption -> tenant isolation -> list
 * transactions/rewards.
 */
class LoyaltyCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullEarnAndRedeemScenario(): void
    {
        $this->seed(LoyaltyCapabilitiesSeeder::class);

        [$tenantA, $agentA, $tokenA] = $this->registerAgentWithPermissions([
            'loyalty.accounts.create', 'loyalty.accounts.read',
            'loyalty.points.manage', 'loyalty.points.redeem',
            'loyalty.rewards.manage', 'loyalty.rewards.read',
            'loyalty.transactions.read',
        ]);

        // Step 1: a Customer.
        $customer = app(CreateCustomerAction::class)->execute($tenantA, 'Jane', 'Doe', 'jane.doe@example.com');

        // Step 2: open a LoyaltyAccount for the Customer via MCP.
        $createAccount = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.account.create',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $createAccount->assertStatus(200);
        $this->assertSame(0, $createAccount->json('data.account.currentBalance'));

        // Step 3: a $50 Product, ordering 3 units — stock stays well above
        // half of on-hand (CheckInventoryAction's re-check math quirk,
        // HANDOFF §8.22), same margin Workflows' own end-to-end test uses.
        $product = app(CreateProductAction::class)->execute($tenantA, 'Gadget', 'GADGET-1', 5000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));

        $cart = app(AddToCartAction::class)->execute(
            tenantId: $tenantA,
            ownerType: MemberType::Agent,
            ownerId: $agentA,
            productId: $product->id,
            quantity: 3,
        );

        // Steps 4-6: placing the Order dispatches OrderWasPlaced ->
        // OrderPlacedListener -> EarnPointsAction, all synchronously,
        // inside PlaceOrderAction. Total is $150.00 (15000 cents) -> 150 points.
        $order = app(PlaceOrderAction::class)->execute(
            tenantId: $tenantA,
            agentId: $agentA,
            cartId: $cart->id,
            customerId: $customer->id,
        );
        $this->assertSame(15000, $order->totalAmount);

        // Step 7: confirm the balance via MCP.
        $getAccount = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.account.get',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $getAccount->assertStatus(200);
        $this->assertSame(150, $getAccount->json('data.account.currentBalance'));
        $this->assertSame(150, $getAccount->json('data.account.totalPointsEarned'));

        // Step 8: a Reward costing 100 points.
        $createReward = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.reward.create',
            'input' => [
                'name' => '$5 Off Coupon',
                'reward_type' => 'discount_coupon',
                'points_required' => 100,
                'discount_amount' => 500,
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $createReward->assertStatus(200);
        $rewardId = $createReward->json('data.reward.id');

        // Step 9: redeem it — 100 points spent, balance 150 -> 50.
        $redeem = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.points.redeem',
            'input' => ['customer_id' => $customer->id, 'points' => 100, 'reward_id' => $rewardId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $redeem->assertStatus(200);
        $this->assertSame(50, $redeem->json('data.new_balance'));
        $this->assertSame('completed', $redeem->json('data.redemption.status'));

        // Step 10: attempting to redeem a 200-point Reward with only 50
        // points left is a real, legitimate business-rule conflict —
        // InsufficientPointsException (409 CONFLICT), not a mismatched
        // price (RedeemPointsAction's own docblock: the `points` input is
        // validated against the Reward's own points_required first, and
        // 200 does match this second Reward's price tag exactly).
        $expensiveReward = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.reward.create',
            'input' => ['name' => 'Free Shipping', 'reward_type' => 'free_shipping', 'points_required' => 200],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $expensiveRewardId = $expensiveReward->json('data.reward.id');

        $insufficientRedeem = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.points.redeem',
            'input' => ['customer_id' => $customer->id, 'points' => 200, 'reward_id' => $expensiveRewardId],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $insufficientRedeem->assertStatus(409);
        $insufficientRedeem->assertJsonPath('error.code', 'CONFLICT');

        // Step 11: Tenant B's Agent cannot see Tenant A's Customer's LoyaltyAccount.
        [, , $tokenB] = $this->registerAgentWithPermissions(['loyalty.accounts.read']);

        $crossTenant = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.account.get',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenant->assertStatus(404);
        $crossTenant->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 12: list transactions for the Customer — one earn, one redeem.
        $transactions = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.transaction.list',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $transactions->assertStatus(200);
        $rows = $transactions->json('data.transactions');
        $this->assertCount(2, $rows);
        $this->assertSame(-100, $rows[0]['points']); // most recent first: the redeem
        $this->assertSame(150, $rows[1]['points']); // then the earn

        // Step 13: list Rewards filtered by is_active.
        $rewards = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.reward.list',
            'input' => ['is_active' => true],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $rewards->assertStatus(200);
        $names = collect($rewards->json('data.rewards'))->pluck('name');
        $this->assertTrue($names->contains('$5 Off Coupon'));
        $this->assertTrue($names->contains('Free Shipping'));
    }

    public function test_earnPoints_forCustomerWithNoExistingAccount_autoCreatesOne(): void
    {
        $this->seed(LoyaltyCapabilitiesSeeder::class);
        [$tenantId, , $token] = $this->registerAgentWithPermissions(['loyalty.points.manage']);

        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Auto', 'Created', 'auto@example.com');

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.points.earn',
            'input' => ['customer_id' => $customer->id, 'points' => 25, 'description' => 'Manual bonus'],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $this->assertSame(25, $response->json('data.new_balance'));
    }

    public function test_createAccount_whenOneAlreadyExists_returnsConflict(): void
    {
        $this->seed(LoyaltyCapabilitiesSeeder::class);
        [$tenantId, , $token] = $this->registerAgentWithPermissions(['loyalty.accounts.create']);

        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Dup', 'Licate', 'dup@example.com');

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.account.create',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$token}"])->assertStatus(200);

        $second = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.account.create',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$token}"]);

        $second->assertStatus(409);
        $second->assertJsonPath('error.code', 'CONFLICT');
    }

    public function test_redeemPoints_withPointsNotMatchingRewardPrice_returnsValidationError(): void
    {
        $this->seed(LoyaltyCapabilitiesSeeder::class);
        [$tenantId, , $token] = $this->registerAgentWithPermissions([
            'loyalty.accounts.create', 'loyalty.points.manage', 'loyalty.points.redeem', 'loyalty.rewards.manage',
        ]);

        $customer = app(CreateCustomerAction::class)->execute($tenantId, 'Mis', 'Match', 'mismatch@example.com');
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.account.create',
            'input' => ['customer_id' => $customer->id],
        ], ['Authorization' => "Bearer {$token}"]);
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.points.earn',
            'input' => ['customer_id' => $customer->id, 'points' => 500],
        ], ['Authorization' => "Bearer {$token}"]);

        $reward = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.reward.create',
            'input' => ['name' => 'Mismatch Reward', 'reward_type' => 'free_product', 'points_required' => 100],
        ], ['Authorization' => "Bearer {$token}"]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.points.redeem',
            'input' => ['customer_id' => $customer->id, 'points' => 150, 'reward_id' => $reward->json('data.reward.id')],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_createReward_withoutPermission_returnsForbidden(): void
    {
        $this->seed(LoyaltyCapabilitiesSeeder::class);
        [, , $token] = $this->registerAgentWithPermissions([]);

        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'loyalty.reward.create',
            'input' => ['name' => 'X', 'reward_type' => 'free_shipping', 'points_required' => 10],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: int, 2: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Ops Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Ops Operator', 'ops-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $agent->id, $token];
    }
}
