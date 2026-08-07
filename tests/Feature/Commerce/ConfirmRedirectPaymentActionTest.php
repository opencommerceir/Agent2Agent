<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CalculatePricingAction;
use App\Modules\Commerce\Application\Actions\ConfirmRedirectPaymentAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\InitiatePaymentAction;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Entities\PaymentSession;
use App\Modules\Commerce\Domain\Exceptions\PaymentSessionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `MockRedirectPaymentGateway::verify()` reads the decline trigger back
 * out of the fake `providerReference` `initiate()` itself encoded
 * (`simulate_failure` metadata, see that class's own docblock) — the
 * same "trigger a real decline without real network mocking" convention
 * `MockPaymentGateway`'s own `simulate_failure` already establishes for
 * the synchronous path.
 */
class ConfirmRedirectPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_onSuccessfulVerify_placesOrderAndRecordsPayment(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $initiated = app(InitiatePaymentAction::class)->execute($tenantId, $agentId, $cart->id, 'mock');

        $result = app(ConfirmRedirectPaymentAction::class)->execute($initiated['tracking_reference'], $tenantId);

        $this->assertTrue($result['successful']);
        $this->assertSame(10900, $result['order']->totalAmount);
        $this->assertSame('confirmed', $result['order']->status);
        $this->assertSame(10900, $result['payment']->amount);
        $this->assertSame('completed', $result['payment']->status);
        $this->assertSame('mock', $result['payment']->gateway);

        $this->assertDatabaseHas('payment_sessions', [
            'id' => $initiated['tracking_reference'],
            'status' => 'completed',
            'order_id' => $result['order']->id,
        ]);

        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($productId, $tenantId);
        $this->assertSame(9, $inventory->quantityOnHand());
    }

    public function test_execute_appliesTheFrozenCouponOnSuccess(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $coupon = app(CreateCouponAction::class)->execute($tenantId, 'COUPON-AB12C', 'percentage', 10);
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $initiated = app(InitiatePaymentAction::class)->execute(
            tenantId: $tenantId, agentId: $agentId, cartId: $cart->id, gatewayName: 'mock', couponCode: $coupon->code,
        );

        $result = app(ConfirmRedirectPaymentAction::class)->execute($initiated['tracking_reference'], $tenantId);

        $this->assertSame(9900, $result['order']->totalAmount);
        $this->assertDatabaseHas('coupons', ['code' => 'COUPON-AB12C', 'used_count' => 1]);
        $this->assertDatabaseHas('discounts', ['order_id' => $result['order']->id, 'discount_amount' => 1000]);
    }

    public function test_execute_onDeclinedVerify_marksSessionFailedAndPlacesNoOrder(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $initiated = $this->initiateWithSimulatedFailure($tenantId, $agentId, $cart->id);

        $result = app(ConfirmRedirectPaymentAction::class)->execute($initiated['tracking_reference'], $tenantId);

        $this->assertFalse($result['successful']);
        $this->assertNull($result['order']);
        $this->assertNull($result['payment']);

        $this->assertDatabaseHas('payment_sessions', ['id' => $initiated['tracking_reference'], 'status' => 'failed']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);

        // The Cart's own reservation must still be intact.
        $inventory = app(InventoryRepositoryInterface::class)->findByProduct($productId, $tenantId);
        $this->assertSame(10, $inventory->quantityOnHand());
        $this->assertSame(1, $inventory->quantityReserved());
    }

    public function test_execute_whenAlreadyCompleted_isIdempotentAndDoesNotDoubleProcess(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $initiated = app(InitiatePaymentAction::class)->execute($tenantId, $agentId, $cart->id, 'mock');

        $first = app(ConfirmRedirectPaymentAction::class)->execute($initiated['tracking_reference'], $tenantId);
        $second = app(ConfirmRedirectPaymentAction::class)->execute($initiated['tracking_reference'], $tenantId);

        $this->assertTrue($second['successful']);
        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame('Payment already confirmed.', $second['message']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_execute_forNonexistentSession_throwsPaymentSessionNotFoundException(): void
    {
        [$tenantId] = $this->setUpTenantWithHundredDollarProduct();

        $this->expectException(PaymentSessionNotFoundException::class);

        app(ConfirmRedirectPaymentAction::class)->execute(999999, $tenantId);
    }

    public function test_execute_forSessionInAnotherTenant_throwsPaymentSessionNotFoundException(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $initiated = app(InitiatePaymentAction::class)->execute($tenantId, $agentId, $cart->id, 'mock');

        $otherTenant = app(CreateTenantAction::class)->execute('Other Inc', 'other-'.uniqid());

        $this->expectException(PaymentSessionNotFoundException::class);

        app(ConfirmRedirectPaymentAction::class)->execute($initiated['tracking_reference'], $otherTenant->id);
    }

    /**
     * @return array{tracking_reference: int, gateway: string, redirect_url: string}
     */
    private function initiateWithSimulatedFailure(int $tenantId, int $agentId, int $cartId): array
    {
        // InitiatePaymentAction doesn't expose a simulate-failure input
        // (a real gateway has no such concept) — MockRedirectPaymentGateway's
        // own decline trigger lives entirely in initiate()'s own
        // $metadata, which InitiatePaymentAction never threads a caller
        // flag into. So this test reaches it directly through the
        // Action's own real collaborators, the same way a genuine gateway
        // decline would only ever surface at verify() time regardless of
        // what initiate() was told.
        $pricing = app(CalculatePricingAction::class)->execute($tenantId, $agentId, $cartId);
        $sessions = app(PaymentSessionRepositoryInterface::class);

        $session = PaymentSession::create(
            tenantId: $tenantId,
            cartId: $cartId,
            agentId: $agentId,
            gateway: 'mock',
            total: Money::fromAmount($pricing->totalAmount, $pricing->totalCurrency),
            tax: Money::fromAmount($pricing->taxAmount, $pricing->totalCurrency),
            discount: Money::fromAmount($pricing->discountAmount, $pricing->totalCurrency),
        );
        $session = $sessions->save($session);

        $gateway = app(PaymentGatewayRegistry::class)->get('mock');
        $initiation = $gateway->initiate($session->total(), 'https://app.test/callback', ['simulate_failure' => true]);

        $session->markInitiated($initiation->providerReference);
        $sessions->save($session);

        return ['tracking_reference' => $session->id(), 'gateway' => 'mock', 'redirect_url' => $initiation->redirectUrl];
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
