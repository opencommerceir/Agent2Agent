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
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\GetDiscountRuleAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\CouponCode;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Coupon -> DiscountRule bypass (Phase 5, Stage 4, §7.24):
 * CalculatePricingAction/ProcessPaymentAction defer to DiscountCalculator
 * against the linked rule instead of Coupon::calculateDiscount() whenever
 * Coupon::discountRuleId() is set, and ApplyCouponAction durably records
 * both sides (Coupon.usedCount + Discount row + DiscountRule.usedCount)
 * only once a checkout genuinely succeeds — never during a mere preview,
 * mirroring the pre-existing Coupon-only invariant CheckoutCapabilityTest
 * already covers.
 */
class CouponDiscountRuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricingPreview_withRuleLinkedCoupon_usesDiscountCalculatorNotCouponsOwnValues(): void
    {
        [$tenantA, $tokenA, $cartId, $productA] = $this->setUpCartWithTwoUnitsAt5000();

        // 20% rule vs. the Coupon's own (deliberately unrelated) fixed_amount=1.
        $rule = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantA,
            name: '20% off via rule',
            discountType: 'percentage',
            discountValue: 20,
            priority: 10,
            stackability: 'coupon_only',
        );

        app(CreateCouponAction::class)->execute(
            tenantId: $tenantA,
            code: 'COUPON-RUL01',
            discountType: 'fixed_amount',
            discountValue: 1,
            discountRuleId: $rule->id,
        );

        $calc = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId, 'coupon_code' => 'COUPON-RUL01'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $calc->assertStatus(200);
        // Subtotal is 10000 (2 x 5000); 20% of that is 2000 -- the rule's
        // own answer, not the Coupon's own fixed_amount=1.
        $calc->assertJsonPath('data.pricing.discountAmount', 2000);
    }

    public function test_fullCheckout_withRuleLinkedPercentageCoupon_recordsDiscountRowAndBothUsageCounters(): void
    {
        [$tenantA, $tokenA, $cartId] = $this->setUpCartWithTwoUnitsAt5000();

        $rule = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantA,
            name: '20% off via rule',
            discountType: 'percentage',
            discountValue: 20,
            priority: 10,
            stackability: 'coupon_only',
        );

        app(CreateCouponAction::class)->execute(
            tenantId: $tenantA,
            code: 'COUPON-RUL02',
            discountType: 'fixed_amount',
            discountValue: 1,
            discountRuleId: $rule->id,
        );

        $process = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.process',
            'input' => [
                'cart_id' => $cartId,
                'coupon_code' => 'COUPON-RUL02',
                'payment_method' => 'credit_card',
                'payment_details' => ['card_number' => '4242424242424242'],
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $process->assertStatus(200);
        $orderId = $process->json('data.order.id');

        $discounts = app(DiscountRepositoryInterface::class)->listByOrder($orderId);
        $this->assertCount(1, $discounts);
        $this->assertSame($rule->id, $discounts[0]->discountRuleId());
        $this->assertSame(2000, $discounts[0]->amount()->amount());

        $fetchedRule = app(GetDiscountRuleAction::class)->execute($rule->id, $tenantA);
        $this->assertSame(1, $fetchedRule->usedCount);

        $coupon = app(CouponRepositoryInterface::class)->findByCode(new CouponCode('COUPON-RUL02'), $tenantA);
        $this->assertSame(1, $coupon->usedCount());
    }

    public function test_pricingPreview_withRuleLinkedCoupon_neverIncrementsTheRulesUsedCount(): void
    {
        [$tenantA, $tokenA, $cartId] = $this->setUpCartWithTwoUnitsAt5000();

        $rule = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantA,
            name: '20% off via rule',
            discountType: 'percentage',
            discountValue: 20,
            priority: 10,
            stackability: 'coupon_only',
        );

        app(CreateCouponAction::class)->execute(
            tenantId: $tenantA,
            code: 'COUPON-RUL03',
            discountType: 'fixed_amount',
            discountValue: 1,
            discountRuleId: $rule->id,
        );

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId, 'coupon_code' => 'COUPON-RUL03'],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        $fetchedRule = app(GetDiscountRuleAction::class)->execute($rule->id, $tenantA);
        $this->assertSame(0, $fetchedRule->usedCount);

        $coupon = app(CouponRepositoryInterface::class)->findByCode(new CouponCode('COUPON-RUL03'), $tenantA);
        $this->assertSame(0, $coupon->usedCount());
    }

    public function test_couponWithNoDiscountRuleId_computesDiscountExactlyAsBefore(): void
    {
        [$tenantA, $tokenA, $cartId] = $this->setUpCartWithTwoUnitsAt5000();

        // No discountRuleId at all -- the legacy shape every pre-existing
        // Coupon has. 10% of a 10000 subtotal is 1000, computed entirely
        // by Coupon::calculateDiscount(), never touching DiscountCalculator.
        app(CreateCouponAction::class)->execute(
            tenantId: $tenantA,
            code: 'COUPON-LEG01',
            discountType: 'percentage',
            discountValue: 10,
        );

        $calc = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId, 'coupon_code' => 'COUPON-LEG01'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $calc->assertStatus(200);
        $calc->assertJsonPath('data.pricing.discountAmount', 1000);
    }

    public function test_fullCheckout_withRuleLinkedBuyXGetYCoupon_grantsCheapestUnitsFree(): void
    {
        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'commerce.cart.manage', 'commerce.checkout.read', 'commerce.checkout.create',
        ]);

        // Two different-priced products so "cheapest unit free" is provable:
        // 3000 x 2 and 7000 x 1 -- 3 units total, subtotal 13000.
        $cheap = app(CreateProductAction::class)->execute($tenantA, 'Cheap Item', 'CHEAP-1', 3000, 'USD', status: 'active');
        $pricey = app(CreateProductAction::class)->execute($tenantA, 'Pricey Item', 'PRICEY-1', 7000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $cheap->id, 10));
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $pricey->id, 10));

        $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $cheap->id, 'quantity' => 2],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        $addPricey = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $pricey->id, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $addPricey->assertStatus(200);
        $cartId = $addPricey->json('data.cart.id');

        // Buy X Get 1: grants the single cheapest unit in the Cart free -- 3000.
        $rule = app(CreateDiscountRuleAction::class)->execute(
            tenantId: $tenantA,
            name: 'Get 1 free (cheapest)',
            discountType: 'buy_x_get_y',
            discountValue: 1,
            priority: 10,
            stackability: 'coupon_only',
        );

        app(CreateCouponAction::class)->execute(
            tenantId: $tenantA,
            code: 'COUPON-BXGY1',
            discountType: 'fixed_amount',
            discountValue: 1,
            discountRuleId: $rule->id,
        );

        $process = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.process',
            'input' => [
                'cart_id' => $cartId,
                'coupon_code' => 'COUPON-BXGY1',
                'payment_method' => 'credit_card',
                'payment_details' => ['card_number' => '4242424242424242'],
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $process->assertStatus(200);
        $orderId = $process->json('data.order.id');

        $discounts = app(DiscountRepositoryInterface::class)->listByOrder($orderId);
        $this->assertCount(1, $discounts);
        $this->assertSame($rule->id, $discounts[0]->discountRuleId());
        $this->assertSame(3000, $discounts[0]->amount()->amount());

        $fetchedRule = app(GetDiscountRuleAction::class)->execute($rule->id, $tenantA);
        $this->assertSame(1, $fetchedRule->usedCount);
    }

    /**
     * Shared fixture for the percentage-rule tests: one product at 5000,
     * two units in the Cart, subtotal 10000.
     *
     * @return array{0: int, 1: string, 2: int, 3: object}
     */
    private function setUpCartWithTwoUnitsAt5000(): array
    {
        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'commerce.cart.manage', 'commerce.checkout.read', 'commerce.checkout.create',
        ]);

        $product = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'WIDGET-1', 5000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $product->id, 10));

        $add = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $product->id, 'quantity' => 2],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $add->assertStatus(200);
        $cartId = $add->json('data.cart.id');

        return [$tenantA, $tokenA, $cartId, $product];
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agent = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping');
        app(AddMemberToOrganizationAction::class)->execute($organization->id, MemberType::Agent, $agent->id);

        if ($permissionKeys !== []) {
            $role = app(CreateRoleAction::class)->execute($tenant->id, 'Shopper', 'shopper-'.uniqid());

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
