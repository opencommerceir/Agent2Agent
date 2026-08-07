<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\InitiatePaymentAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\CartNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `InitiatePaymentAction` composes `CalculatePricingAction` for pricing
 * (§7.37) — the $100 subtotal / 9% tax / $109 total worked example is
 * the same one `ProcessPaymentTest`/`CheckoutCapabilityTest` already
 * establish, confirming both checkout paths agree on the exact same
 * numbers for the exact same Cart.
 */
class InitiatePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withMockGateway_returnsRedirectUrlAndPersistsPendingSession(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $result = app(InitiatePaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: $cart->id,
            gatewayName: 'mock',
        );

        $this->assertSame('mock', $result['gateway']);
        $this->assertIsInt($result['tracking_reference']);
        $this->assertStringContainsString((string) $result['tracking_reference'], $result['redirect_url']);

        $this->assertDatabaseHas('payment_sessions', [
            'id' => $result['tracking_reference'],
            'tenant_id' => $tenantId,
            'cart_id' => $cart->id,
            'gateway' => 'mock',
            'status' => 'pending',
            'total_amount' => 10900,
            'tax_amount' => 900,
            'discount_amount' => 0,
            'currency' => 'USD',
        ]);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_execute_withCoupon_freezesTheDiscountOnTheSession(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $coupon = app(CreateCouponAction::class)->execute($tenantId, 'COUPON-AB12C', 'percentage', 10);
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $result = app(InitiatePaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: $cart->id,
            gatewayName: 'mock',
            couponCode: $coupon->code,
        );

        $this->assertDatabaseHas('payment_sessions', [
            'id' => $result['tracking_reference'],
            'coupon_code' => 'COUPON-AB12C',
            'discount_amount' => 1000,
            'total_amount' => 9900,
        ]);
        // A mere initiate never consumes the Coupon's own usage — only a
        // later, successful confirm does (mirrors ApplyCouponAction only
        // ever running after a real charge succeeds).
        $this->assertDatabaseHas('coupons', ['code' => 'COUPON-AB12C', 'used_count' => 0]);
    }

    public function test_execute_defaultsToTheConfiguredGateway_whenNoneGiven(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $result = app(InitiatePaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: $cart->id,
        );

        $this->assertSame('mock', $result['gateway']); // PAYMENT_GATEWAY=mock, phpunit.xml
    }

    public function test_execute_forNonexistentCart_throwsCartNotFoundException(): void
    {
        [$tenantId, $agentId] = $this->setUpTenantWithHundredDollarProduct();

        $this->expectException(CartNotFoundException::class);

        app(InitiatePaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: 999999,
        );
    }

    public function test_execute_neverReservesOrCommitsInventoryItself(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        app(InitiatePaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: $cart->id,
            gatewayName: 'mock',
        );

        // Reservation already happened at AddToCartAction time (Cart
        // lifecycle unchanged by this feature, §7.37) — initiating a
        // redirect charge neither reserves again nor commits.
        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($productId, $tenantId);
        $this->assertSame(10, $inventory->quantityOnHand());
        $this->assertSame(1, $inventory->quantityReserved());
    }

    /**
     * @return array{0: int, 1: int, 2: int} tenantId, agentId, productId (priced at $100, stocked at 10)
     */
    private function setUpTenantWithHundredDollarProduct(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $organization = app(CreateOrganizationAction::class)->execute($tenant->id, 'Acme Store', 'acme-store-'.uniqid());
        $agentId = app(RegisterAgentAction::class)->execute($tenant->id, $organization->id, 'Shopping Assistant', 'shopping')->id;

        $product = app(CreateProductAction::class)->execute($tenant->id, 'Widget', 'WIDGET-1', 10000, 'USD', status: 'active');
        app(InventoryRepositoryInterface::class)->save(Inventory::stock($tenant->id, $product->id, 10));

        return [$tenant->id, $agentId, $product->id];
    }
}
