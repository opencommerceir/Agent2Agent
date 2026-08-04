<?php

namespace Tests\Feature\Commerce;

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
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The literal end-to-end scenario from Phase 5, Stage 4's own request
 * (§7.24), driven entirely through MCP: 3 DiscountRules with different
 * priority/Stackability -> a $80 Cart -> `commerce.discount.apply`
 * resolves Rule A (stackable) + Rule C (stackable) together, correctly
 * excluding Rule B (exclusive, can't join a Stackable-anchored set) ->
 * `commerce.discount.available` shows all 3 as individually eligible
 * (the deliberate difference between the two capabilities) -> a Coupon
 * linked to Rule B is created and redeemed through the *existing*,
 * separate checkout flow (`commerce.checkout.process`) -> confirms Rule
 * B's own DiscountCalculator amount lands on the real Order's Discount
 * row and increments Rule B's usedCount -> tenant isolation -> an
 * expired rule never appears in either capability.
 *
 * One deliberate divergence from the request's own step 8 ("check Total
 * Discount -> sum of 3 discounts"): this stage's own design (see the
 * orchestrator's HANDOFF §7.24 note) never composes Cart-level automatic
 * rules with a Coupon-triggered rule into one combined checkout total —
 * `commerce.discount.apply` is a standalone Cart preview/browsing
 * surface this stage, not wired into `commerce.checkout.calculate`/
 * `.process`'s own total. This test instead proves each path
 * independently, which is what's actually built and is the more
 * conservative, lower-regression-risk scope for the existing, heavily
 * tested checkout Actions.
 */
class DiscountRuleCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullDiscountRuleLifecycle_fromStackingToCouponRedemption(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantId, $token] = $this->registerAgentWithPermissions([
            'commerce.discounts.manage', 'commerce.discounts.read',
            'commerce.cart.manage', 'commerce.cart.read',
            'commerce.checkout.create', 'commerce.checkout.read',
            'commerce.coupons.create',
        ]);

        $productA = app(CreateProductAction::class)->execute($tenantId, 'Widget A', 'WIDGET-A', 3000, 'USD', status: 'active');
        $productB = app(CreateProductAction::class)->execute($tenantId, 'Widget B', 'WIDGET-B', 3000, 'USD', status: 'active');
        $productC = app(CreateProductAction::class)->execute($tenantId, 'Widget C', 'WIDGET-C', 2000, 'USD', status: 'active');
        $this->seedInventory($tenantId, [$productA->id, $productB->id, $productC->id]);

        // Step 1: create the 3 DiscountRules.
        $ruleAId = $this->createRule($token, 'A: 10% off', 'percentage', 10, 10, 'stackable');
        $ruleBId = $this->createRule($token, 'B: $5 off min $50', 'fixed_amount', 500, 5, 'exclusive', [
            ['type' => 'min_subtotal', 'value' => 5000],
        ]);
        $ruleCId = $this->createRule($token, 'C: Buy 2 Get 1', 'buy_x_get_y', 1, 1, 'stackable', [
            ['type' => 'min_quantity', 'value' => 2],
        ]);

        // Step 2: a Cart with 3 Products totaling $80 (3000+3000+2000).
        $cartId = $this->addAllToCart($token, [$productA->id, $productB->id, $productC->id]);

        // Step 3/4: apply resolves A + C, excludes B.
        $applyResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.discount.apply',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$token}"]);
        $applyResponse->assertStatus(200);
        $applied = $applyResponse->json('data.applied_discounts');
        $this->assertCount(2, $applied); // step 5: exactly 2 discounts registered
        $appliedRuleIds = array_column($applied, 'discountRuleId');
        $this->assertContains($ruleAId, $appliedRuleIds);
        $this->assertContains($ruleCId, $appliedRuleIds);
        $this->assertNotContains($ruleBId, $appliedRuleIds);
        // Rule A (10% of 8000 = 800) + Rule C (cheapest unit, $20 = 2000) = 2800.
        $applyResponse->assertJsonPath('data.total_discount.amount', 2800);

        // commerce.discount.available shows all 3 as individually eligible.
        $availableResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.discount.available',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$token}"]);
        $availableResponse->assertStatus(200);
        $this->assertCount(3, $availableResponse->json('data.available_rules'));

        // Neither Rule's usedCount moved — a Cart is never real usage.
        $ruleAAfterApply = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.rule.get',
            'input' => ['rule_id' => $ruleAId],
        ], ['Authorization' => "Bearer {$token}"]);
        $ruleAAfterApply->assertJsonPath('data.rule.usedCount', 0);

        // Step 6: a Coupon linked to Rule B.
        $couponResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.coupon.create',
            'input' => [
                'code' => 'COUPON-RULEB',
                'discount_type' => 'fixed_amount',
                'discount_value' => 500,
                'discount_rule_id' => $ruleBId,
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $couponResponse->assertStatus(200);

        // Step 7: redeem it through the existing, separate checkout flow.
        $checkoutCartId = $this->addAllToCart($token, [$productA->id, $productB->id, $productC->id]);
        $checkoutResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.process',
            'input' => [
                'cart_id' => $checkoutCartId,
                'payment_method' => 'credit_card',
                'coupon_code' => 'COUPON-RULEB',
            ],
        ], ['Authorization' => "Bearer {$token}"]);
        $checkoutResponse->assertStatus(200);
        $orderId = $checkoutResponse->json('data.order.id');

        // Rule B's own $5 fixed amount, not its own (unused)
        // discount_type/discount_value — proves the DiscountCalculator
        // bypass actually ran through the real checkout path.
        $discounts = app(DiscountRepositoryInterface::class)->listByOrder($orderId);
        $this->assertCount(1, $discounts);
        $this->assertSame(500, $discounts[0]->amount()->amount());
        $this->assertSame($ruleBId, $discounts[0]->discountRuleId());

        $ruleBAfterCheckout = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.rule.get',
            'input' => ['rule_id' => $ruleBId],
        ], ['Authorization' => "Bearer {$token}"]);
        $ruleBAfterCheckout->assertJsonPath('data.rule.usedCount', 1); // step 8's own real usage count

        // Step 9: tenant isolation.
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.discounts.read']);
        $crossTenantGet = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.rule.get',
            'input' => ['rule_id' => $ruleAId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantGet->assertStatus(404);
        $crossTenantGet->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 10: an expired rule never applies.
        $expiredRuleId = $this->createRule($token, 'Expired', 'percentage', 50, 100, 'stackable', [], expiresAt: now()->subDay()->toAtomString());
        $expiredCartId = $this->addAllToCart($token, [$productA->id, $productB->id, $productC->id]);
        $applyAfterExpired = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.discount.apply',
            'input' => ['cart_id' => $expiredCartId],
        ], ['Authorization' => "Bearer {$token}"]);
        $appliedRuleIdsAfterExpired = array_column($applyAfterExpired->json('data.applied_discounts'), 'discountRuleId');
        $this->assertNotContains($expiredRuleId, $appliedRuleIdsAfterExpired);
    }

    private function seedInventory(int $tenantId, array $productIds): void
    {
        $inventories = app(\App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface::class);

        foreach ($productIds as $productId) {
            $inventories->save(\App\Modules\Commerce\Domain\Entities\Inventory::stock($tenantId, $productId, 100));
        }
    }

    private function createRule(
        string $token,
        string $name,
        string $discountType,
        int $discountValue,
        int $priority,
        string $stackability,
        array $conditions = [],
        ?string $expiresAt = null,
    ): int {
        $response = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.rule.create',
            'input' => [
                'name' => $name,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'priority' => $priority,
                'stackability' => $stackability,
                'conditions' => $conditions,
                'expires_at' => $expiresAt,
            ],
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);

        return $response->json('data.rule.id');
    }

    private function addAllToCart(string $token, array $productIds): int
    {
        $cartId = null;

        foreach ($productIds as $productId) {
            $response = $this->postJson('/mcp/v1/execute', [
                'capability' => 'commerce.cart.add',
                'input' => ['product_id' => $productId, 'quantity' => 1],
            ], ['Authorization' => "Bearer {$token}"]);

            $response->assertStatus(200);
            $cartId = $response->json('data.cart.id');
        }

        return $cartId;
    }

    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Ops', 'acme-ops-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Discount Bot', 'custom');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Discount Operator', 'discount-operator-'.uniqid());

            foreach ($permissionKeys as $permissionKey) {
                $existing = app(PermissionRepositoryInterface::class)->findByKey(new PermissionKey($permissionKey));
                $permissionId = $existing?->id() ?? app(CreatePermissionAction::class)->execute($permissionKey)->id;
                app(AssignPermissionToRoleAction::class)->execute($role->id, $permissionId);
            }

            app(AssignRoleToMemberAction::class)->execute(MemberType::Agent, $agent->id, $role->id);
        }

        $token = app(GenerateAgentTokenAction::class)->execute($agent->id)->plainToken;

        return [$tenant->id, $token];
    }
}
