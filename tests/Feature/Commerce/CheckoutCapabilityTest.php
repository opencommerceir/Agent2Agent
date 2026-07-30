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
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Database\Seeders\CommerceCapabilitiesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full stage-5 end-to-end scenario over real MCP HTTP requests: a
 * $100 cart (two products), a pricing preview with and without a 10%
 * coupon, a successful checkout, a refund, an expired-coupon rejection,
 * and tenant isolation — proven here via `commerce.payment.refund`
 * (the only MCP-exposed capability that takes a payment_id), since no
 * `commerce.payment.get` capability was requested this stage.
 */
class CheckoutCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fullCheckoutScenario(): void
    {
        $this->seed(CommerceCapabilitiesSeeder::class);

        [$tenantA, $tokenA] = $this->registerAgentWithPermissions([
            'commerce.cart.manage', 'commerce.checkout.read', 'commerce.checkout.create',
            'commerce.coupons.create', 'commerce.payments.refund',
        ]);
        [, $tokenB] = $this->registerAgentWithPermissions(['commerce.payments.refund']);

        $productA = app(CreateProductAction::class)->execute($tenantA, 'Widget', 'WIDGET-1', 6000, 'USD', status: 'active');
        $productB = app(CreateProductAction::class)->execute($tenantA, 'Gadget', 'GADGET-1', 4000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $productA->id, 10));
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenantA, $productB->id, 10));

        // Step 1: cart with two products totaling $100.
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $productA->id, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        $addB = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $productB->id, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $addB->assertStatus(200);
        $cartId = $addB->json('data.cart.id');

        // Step 2: pricing without a coupon -> subtotal 100, tax 9, total 109.
        $calcNoCoupon = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $calcNoCoupon->assertStatus(200);
        $calcNoCoupon->assertJsonPath('data.pricing.subtotalAmount', 10000);
        $calcNoCoupon->assertJsonPath('data.pricing.taxAmount', 900);
        $calcNoCoupon->assertJsonPath('data.pricing.totalAmount', 10900);

        // Step 3: create a 10% coupon.
        $couponResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.coupon.create',
            'input' => ['code' => 'COUPON-AB12C', 'discount_type' => 'percentage', 'discount_value' => 10],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $couponResponse->assertStatus(200);

        // Step 4: pricing with the coupon -> discount 10, total 99.
        $calcWithCoupon = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $cartId, 'coupon_code' => 'COUPON-AB12C'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $calcWithCoupon->assertStatus(200);
        $calcWithCoupon->assertJsonPath('data.pricing.discountAmount', 1000);
        $calcWithCoupon->assertJsonPath('data.pricing.totalAmount', 9900);

        // Step 5+6: process the actual checkout with the coupon.
        $processResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.process',
            'input' => [
                'cart_id' => $cartId,
                'coupon_code' => 'COUPON-AB12C',
                'payment_method' => 'credit_card',
                'payment_details' => ['card_number' => '4242424242424242'],
            ],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $processResponse->assertStatus(200);
        $processResponse->assertJsonPath('data.order.status', 'confirmed');
        $processResponse->assertJsonPath('data.order.totalAmount', 9900);
        $processResponse->assertJsonPath('data.payment.status', 'completed');
        $processResponse->assertJsonPath('data.payment.amount', 9900);
        $paymentId = $processResponse->json('data.payment.id');

        // Step 7: inventory was committed (sold), not just reserved.
        $inventoryA = app(InventoryRepositoryInterface::class)->findByProduct($productA->id, $tenantA);
        $this->assertSame(9, $inventoryA->quantityOnHand());

        // Step 10a: a different tenant's Agent must never reach Tenant A's Payment.
        $crossTenantRefund = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.refund',
            'input' => ['payment_id' => $paymentId],
        ], ['Authorization' => "Bearer {$tokenB}"]);
        $crossTenantRefund->assertStatus(404);
        $crossTenantRefund->assertJsonPath('error.code', 'NOT_FOUND');

        // Step 8: refund by the rightful tenant restores inventory and the Order status.
        $refundResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.payment.refund',
            'input' => ['payment_id' => $paymentId, 'reason' => 'Customer changed their mind.'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $refundResponse->assertStatus(200);
        $refundResponse->assertJsonPath('data.payment.status', 'refunded');

        $inventoryAfterRefund = app(InventoryRepositoryInterface::class)->findByProduct($productA->id, $tenantA);
        $this->assertSame(10, $inventoryAfterRefund->quantityOnHand());

        // Step 9: an expired coupon is rejected as a conflict, not silently ignored.
        // The original cart was already consumed by checkout.process above
        // (PlaceOrderAction clears it), so a fresh cart is needed here.
        $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.coupon.create',
            'input' => ['code' => 'COUPON-EXP01', 'discount_type' => 'percentage', 'discount_value' => 5, 'expires_at' => '2020-01-01T00:00:00+00:00'],
        ], ['Authorization' => "Bearer {$tokenA}"])->assertStatus(200);

        $newCartResponse = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.cart.add',
            'input' => ['product_id' => $productA->id, 'quantity' => 1],
        ], ['Authorization' => "Bearer {$tokenA}"]);
        $newCartId = $newCartResponse->json('data.cart.id');

        $expiredCalc = $this->postJson('/mcp/v1/execute', [
            'capability' => 'commerce.checkout.calculate',
            'input' => ['cart_id' => $newCartId, 'coupon_code' => 'COUPON-EXP01'],
        ], ['Authorization' => "Bearer {$tokenA}"]);

        $expiredCalc->assertStatus(409);
        $expiredCalc->assertJsonPath('error.code', 'CONFLICT');
    }

    /**
     * @param list<string> $permissionKeys
     * @return array{0: int, 1: string}
     */
    private function registerAgentWithPermissions(array $permissionKeys): array
    {
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
