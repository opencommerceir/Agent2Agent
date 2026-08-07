<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateOrganizationAction;
use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\Actions\RegisterAgentAction;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CalculatePricingAction;
use App\Modules\Commerce\Application\Actions\CreateProductAction;
use App\Modules\Commerce\Application\Actions\InitiatePaymentAction;
use App\Modules\Commerce\Application\Services\MockRedirectPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use App\Modules\Commerce\Domain\Entities\Inventory;
use App\Modules\Commerce\Domain\Entities\PaymentSession;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentSessionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two public, unauthenticated HTTP routes real gateways actually hit
 * (§7.37) — `routes/payments.php`, no bearer token, no CSRF/session (see
 * that file's own docblock). The `stripe` gateway registration is
 * swapped for a fake `RedirectPaymentGatewayInterface` in each Stripe
 * test's own setup (`PaymentGatewayRegistry::register()`, the identical
 * "re-register a fresh instance directly into the Registry" technique
 * HANDOFF gotcha #11 already documents for `ConnectorRegistry`/
 * `ShippingProviderRegistry`) — this test suite has no real Stripe
 * credentials to call, and the webhook signature itself is exactly what's
 * under test here, not Stripe's own API.
 */
class PaymentCallbackRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_zibalStyleCallback_onSuccess_confirmsAndRendersConfirmedPage(): void
    {
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $initiated = app(InitiatePaymentAction::class)->execute($tenantId, $agentId, $cart->id, 'mock');

        $response = $this->get("/payments/mock/callback?session={$initiated['tracking_reference']}&trackId=999&success=1");

        $response->assertStatus(200);
        $response->assertSee('Payment confirmed');
        $this->assertDatabaseHas('payment_sessions', ['id' => $initiated['tracking_reference'], 'status' => 'completed']);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_callback_withMissingSessionParam_rendersFailedPage(): void
    {
        $response = $this->get('/payments/mock/callback');

        $response->assertStatus(200);
        $response->assertSee('Payment not completed');
    }

    public function test_callback_forNonexistentSession_rendersFailedPageInsteadOfCrashing(): void
    {
        $response = $this->get('/payments/mock/callback?session=999999');

        $response->assertStatus(200);
        $response->assertSee('Payment not completed');
    }

    public function test_callback_neverTrustsTheQueryStringSuccessFlag(): void
    {
        // Zibal's own explicit warning: the callback's own `success=1`
        // must never be trusted — only a real, server-side verify()
        // result decides anything. This session's own providerReference
        // is prefixed to make MockRedirectPaymentGateway::verify()
        // report a decline regardless of the querystring's own claim.
        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $sessionId = $this->createPendingSession($tenantId, $agentId, $cart->id, 'mock', 'mock_declined_forced');

        $response = $this->get("/payments/mock/callback?session={$sessionId}&success=1");

        $response->assertStatus(200);
        $response->assertSee('Payment not completed');
        $this->assertDatabaseHas('payment_sessions', ['id' => $sessionId, 'status' => 'failed']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_stripeWebhook_withValidSignatureAndCompletedSession_confirmsPayment(): void
    {
        config(['payment_gateways.stripe.webhook_secret' => 'whsec_test_secret']);
        app(PaymentGatewayRegistry::class)->register('stripe', new MockRedirectPaymentGateway());

        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $sessionId = $this->createPendingSession($tenantId, $agentId, $cart->id, 'stripe', 'cs_test_webhook_1');

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_webhook_1']],
        ]);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test_secret');

        $response = $this->call(
            'POST',
            '/payments/stripe/webhook',
            [], [], [],
            ['HTTP_Stripe-Signature' => "t={$timestamp},v1={$signature}", 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('payment_sessions', ['id' => $sessionId, 'status' => 'completed']);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_stripeWebhook_withInvalidSignature_returns400AndConfirmsNothing(): void
    {
        config(['payment_gateways.stripe.webhook_secret' => 'whsec_test_secret']);
        app(PaymentGatewayRegistry::class)->register('stripe', new MockRedirectPaymentGateway());

        [$tenantId, $agentId, $productId] = $this->setUpTenantWithHundredDollarProduct();
        $cart = app(AddToCartAction::class)->execute($tenantId, MemberType::Agent, $agentId, $productId, 1);
        $sessionId = $this->createPendingSession($tenantId, $agentId, $cart->id, 'stripe', 'cs_test_webhook_2');

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_webhook_2']],
        ]);

        $response = $this->call(
            'POST',
            '/payments/stripe/webhook',
            [], [], [],
            ['HTTP_Stripe-Signature' => 't='.time().',v1=not_a_real_signature', 'CONTENT_TYPE' => 'application/json'],
            $payload,
        );

        $response->assertStatus(400);
        $this->assertDatabaseHas('payment_sessions', ['id' => $sessionId, 'status' => 'pending']);
        $this->assertDatabaseCount('orders', 0);
    }

    private function createPendingSession(int $tenantId, int $agentId, int $cartId, string $gateway, string $providerReference): int
    {
        $pricing = app(CalculatePricingAction::class)->execute($tenantId, $agentId, $cartId);
        $sessions = app(PaymentSessionRepositoryInterface::class);

        $session = PaymentSession::create(
            tenantId: $tenantId,
            cartId: $cartId,
            agentId: $agentId,
            gateway: $gateway,
            total: Money::fromAmount($pricing->totalAmount, $pricing->totalCurrency),
            tax: Money::fromAmount($pricing->taxAmount, $pricing->totalCurrency),
            discount: Money::fromAmount($pricing->discountAmount, $pricing->totalCurrency),
        );
        $session = $sessions->save($session);
        $session->markInitiated($providerReference);
        $sessions->save($session);

        return $session->id();
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
