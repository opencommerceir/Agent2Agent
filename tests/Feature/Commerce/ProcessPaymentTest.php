<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Exceptions\CouponExpiredException;
use App\Modules\Commerce\Domain\Exceptions\PaymentFailedException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the exact worked example this stage specified: a $100
 * subtotal, 9% tax (=$9, total $109 without a coupon), a 10% coupon
 * (=$10 off, total $99 with it) — and the failure/expiry paths a real
 * checkout has to handle correctly.
 */
class ProcessPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_withoutCoupon_appliesDefaultTaxAndCommitsInventory(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();

        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $result = app(ProcessPaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: $cart->id,
            paymentMethod: 'credit_card',
            paymentDetails: ['card_number' => '4242424242424242'],
        );

        $this->assertSame(10000, $result['order']->subtotalAmount);
        $this->assertSame(900, $result['order']->taxAmount);
        $this->assertSame(0, $result['order']->discountAmount);
        $this->assertSame(10900, $result['order']->totalAmount);
        $this->assertSame('confirmed', $result['order']->status);

        $this->assertSame(10900, $result['payment']->amount);
        $this->assertSame('completed', $result['payment']->status);
        $this->assertNotNull($result['payment']->transactionId);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($productId, $tenantId);
        $this->assertSame(9, $inventory->quantityOnHand()); // started at 10, sold 1
    }

    public function test_execute_withValidCoupon_appliesDiscountAndIncrementsUsage(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $coupon = app(CreateCouponAction::class)->execute($tenantId, 'COUPON-AB12C', 'percentage', 10);
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $result = app(ProcessPaymentAction::class)->execute(
            tenantId: $tenantId,
            agentId: $agentId,
            cartId: $cart->id,
            paymentMethod: 'credit_card',
            paymentDetails: [],
            couponCode: $coupon->code,
        );

        $this->assertSame(900, $result['order']->taxAmount);
        $this->assertSame(1000, $result['order']->discountAmount);
        $this->assertSame(9900, $result['order']->totalAmount); // 10000 + 900 - 1000

        $this->assertDatabaseHas('coupons', ['code' => 'COUPON-AB12C', 'used_count' => 1]);
        $this->assertDatabaseHas('discounts', ['order_id' => $result['order']->id, 'discount_amount' => 1000]);
    }

    public function test_execute_withExpiredCoupon_throwsCouponExpiredExceptionAndChargesNothing(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        app(CreateCouponAction::class)->execute(
            tenantId: $tenantId,
            code: 'COUPON-AB12C',
            discountType: 'percentage',
            discountValue: 10,
            expiresAt: '2020-01-01T00:00:00+00:00',
        );
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $this->expectException(CouponExpiredException::class);

        try {
            app(ProcessPaymentAction::class)->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                cartId: $cart->id,
                paymentMethod: 'credit_card',
                paymentDetails: [],
                couponCode: 'COUPON-AB12C',
            );
        } finally {
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_execute_withDeclinedGateway_throwsPaymentFailedExceptionAndPlacesNoOrder(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);

        $this->expectException(PaymentFailedException::class);

        try {
            app(ProcessPaymentAction::class)->execute(
                tenantId: $tenantId,
                agentId: $agentId,
                cartId: $cart->id,
                paymentMethod: 'credit_card',
                paymentDetails: ['simulate_failure' => true],
            );
        } finally {
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('orders', 0);

            // The Cart's reservation must still be intact — a declined
            // charge must not have committed (sold) any inventory.
            $inventory = app(InventoryRepositoryInterface::class)->findByProduct($productId, $tenantId);
            $this->assertSame(10, $inventory->quantityOnHand());
            $this->assertSame(1, $inventory->quantityReserved());
        }
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
