<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Modules\Commerce\Application\Services\MockRedirectPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The whole browser-facing purchase flow end to end: package picker ->
 * POST initiate -> (simulated) gateway redirect back -> public callback
 * route -> balance credited. Not just PurchaseCreditsAction/
 * ConfirmCreditPurchaseAction in isolation (already covered) — this
 * proves the actual HTTP routes/controllers/guards wire correctly.
 */
class CreditPurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PaymentGatewayRegistry::class)->register('zibal', new MockRedirectPaymentGateway());
    }

    private function verifiedBusinessWithOwner(): array
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $owner = BusinessOwner::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        return [$business, $owner];
    }

    public function test_picker_requiresLogin(): void
    {
        $response = $this->get(route('nexus.credit.purchase.index'));

        $response->assertRedirect(route('nexus.business.login'));
    }

    public function test_picker_showsThreePackages(): void
    {
        [, $owner] = $this->verifiedBusinessWithOwner();

        $response = $this->actingAs($owner, 'business')->get(route('nexus.credit.purchase.index'));

        $response->assertStatus(200);
        $response->assertSee('500,000');
        $response->assertSee('2,000,000');
        $response->assertSee('10,000,000');
    }

    public function test_fullFlow_storeThenCallback_creditsTheBalance(): void
    {
        [$business, $owner] = $this->verifiedBusinessWithOwner();

        $store = $this->actingAs($owner, 'business')->post(route('nexus.credit.purchase.store'), ['package' => 'starter']);
        $store->assertRedirect();
        $redirectUrl = $store->headers->get('Location');
        $this->assertStringStartsWith('https://mock-gateway.test/pay/', $redirectUrl);

        // The mock gateway's own redirect URL echoes the real callback URL
        // (with ?session=<id> already attached) back as its own `callback`
        // query param — pull the session id out of that rather than
        // assuming an auto-increment value.
        parse_str((string) parse_url($redirectUrl, PHP_URL_QUERY), $query);
        parse_str((string) parse_url(urldecode($query['callback']), PHP_URL_QUERY), $callbackQuery);
        $sessionId = (int) $callbackQuery['session'];

        $session = app(CreditPurchaseSessionRepositoryInterface::class)->findByIdUnscoped($sessionId);
        $this->assertNotNull($session);

        $callback = $this->get(route('nexus.credit.purchase.callback', ['gateway' => 'zibal', 'session' => $session->id()]));

        $callback->assertStatus(200);
        $callback->assertSee('1,000');

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($business->id);
        $this->assertSame(1000, $balance->balance());
    }
}
