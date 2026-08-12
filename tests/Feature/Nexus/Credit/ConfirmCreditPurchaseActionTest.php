<?php

namespace Tests\Feature\Nexus\Credit;

use App\Domains\Nexus\Business\Application\Actions\RegisterBusinessAction;
use App\Domains\Nexus\Business\Application\Actions\VerifyBusinessAction;
use App\Domains\Nexus\Business\Domain\ValueObjects\BusinessType;
use App\Domains\Nexus\Business\Domain\ValueObjects\Industry;
use App\Domains\Nexus\Credit\Application\Actions\ConfirmCreditPurchaseAction;
use App\Domains\Nexus\Credit\Application\Actions\PurchaseCreditsAction;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditBalanceRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\Repositories\CreditPurchaseSessionRepositoryInterface;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditPackage;
use App\Modules\Commerce\Application\Services\MockRedirectPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfirmCreditPurchaseActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PaymentGatewayRegistry::class)->register('zibal', new MockRedirectPaymentGateway());
    }

    public function test_execute_onSuccessfulPayment_grantsCreditsAndCompletesSession(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $purchase = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);

        $result = app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $this->assertTrue($result['successful']);
        $this->assertSame(1000, $result['creditsGranted']);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($business->id);
        $this->assertSame(1000, $balance->balance());

        $session = app(CreditPurchaseSessionRepositoryInterface::class)->findById($purchase['tracking_reference'], $business->id);
        $this->assertTrue($session->isCompleted());

        $this->assertDatabaseHas('nexus_credit_transactions', [
            'business_id' => $business->id,
            'type' => 'purchase',
            'amount' => 1000,
            'reason' => 'credit.purchase.starter',
            'related_id' => $purchase['tracking_reference'],
        ]);
    }

    public function test_execute_isIdempotent_onSecondCall(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);
        $purchase = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);
        app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $result = app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $this->assertTrue($result['successful']);
        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($business->id);
        // Not double-granted.
        $this->assertSame(1000, $balance->balance());
    }

    public function test_execute_onDeclinedPayment_grantsNoCreditsAndFailsSession(): void
    {
        $business = app(RegisterBusinessAction::class)->execute('شرکت آزمایشی', 'Test Company', BusinessType::Company, Industry::Technology);
        app(VerifyBusinessAction::class)->execute($business->id);

        // Simulate a decline the same way Commerce's own Mock gateway
        // supports — force it in directly since PurchaseCreditsAction
        // doesn't expose a simulate_failure passthrough (not needed for
        // real callers), then re-point the session at a declined
        // reference the mock gateway will read back as failed.
        $purchase = app(PurchaseCreditsAction::class)->execute($business->id, CreditPackage::Starter);
        $session = app(CreditPurchaseSessionRepositoryInterface::class)->findById($purchase['tracking_reference'], $business->id);
        DB::table('nexus_credit_purchase_sessions')->where('id', $session->id())->update(['provider_reference' => 'mock_declined_test']);

        $result = app(ConfirmCreditPurchaseAction::class)->execute($purchase['tracking_reference']);

        $this->assertFalse($result['successful']);
        $this->assertSame(0, $result['creditsGranted']);

        $balance = app(CreditBalanceRepositoryInterface::class)->findByBusinessId($business->id);
        $this->assertSame(0, $balance->balance());

        $session = app(CreditPurchaseSessionRepositoryInterface::class)->findById($purchase['tracking_reference'], $business->id);
        $this->assertSame('failed', $session->status()->value);
    }
}
