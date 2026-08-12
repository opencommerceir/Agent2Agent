<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\PurchaseCreditsAction;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Modules\Commerce\Application\Services\MockRedirectPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Uses Commerce's own MockRedirectPaymentGateway (registered under the
 * 'zibal' name, overriding NexusServiceProvider's real ZibalPaymentGateway
 * for this test only) — the same "swap the registry entry in setUp()"
 * technique Commerce's own PaymentCallbackRouteTest already establishes,
 * so this never makes a real HTTP call to Zibal.
 */
class PurchaseCreditsActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PaymentGatewayRegistry::class)->register('zibal', new MockRedirectPaymentGateway());
    }

    public function test_execute_initiatesASessionAndReturnsARedirectUrl(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        $result = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);

        $this->assertNotEmpty($result['redirect_url']);
        $this->assertIsInt($result['tracking_reference']);

        $session = app(CreditPurchaseSessionRepositoryInterface::class)->findById($result['tracking_reference'], $business->id);
        $this->assertNotNull($session);
        $this->assertSame('zibal', $session->gateway());
        $this->assertSame(CreditPackage::Starter, $session->package());
        $this->assertSame(500_000, $session->total()->amount());
        $this->assertSame('IRT', $session->total()->currency());
        $this->assertNotNull($session->providerReference());
        $this->assertTrue($session->isPending());
    }

    public function test_execute_withUnsupportedGateway_throws(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        $this->expectException(InvalidArgumentException::class);

        app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter, 'stripe');
    }
}
