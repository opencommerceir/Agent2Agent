<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Core\Application\DTOs\TenantData;
use App\Modules\Commerce\Application\Actions\CancelSubscriptionAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateSubscriptionAction;
use App\Modules\Commerce\Application\Actions\CreateSubscriptionPlanAction;
use App\Modules\Commerce\Application\Actions\PauseSubscriptionAction;
use App\Modules\Commerce\Application\Actions\ResumeSubscriptionAction;
use App\Modules\Commerce\Application\Actions\UpgradeSubscriptionAction;
use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Application\DTOs\SubscriptionPlanData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\PaymentGatewayResult;
use App\Modules\Commerce\Domain\Exceptions\InvalidSubscriptionStateException;
use App\Modules\Commerce\Domain\Exceptions\PaymentFailedException;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\Services\SubscriptionProrationCalculator;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

/**
 * Action-level tests for the "lifecycle actions" slice of Subscriptions
 * (Phase 5, Stage 5) — Pause/Resume/Cancel/Upgrade. Fixtures go through
 * CreateSubscriptionPlanAction/CreateSubscriptionAction rather than
 * hand-constructing entities, the same convention
 * WarehouseTransferActionsTest already establishes for this test tier.
 */
class SubscriptionLifecycleActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_fromActive_setsStatusPausedAndPausedAt(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);

        $paused = app(PauseSubscriptionAction::class)->execute($subscription->id, $tenant->id);

        $this->assertSame('paused', $paused->status);
        $this->assertNotNull($paused->pausedAt);
    }

    public function test_pause_fromTrial_throwsInvalidState(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createTrialSubscription($tenant->id, 5000);

        $this->expectException(InvalidSubscriptionStateException::class);

        app(PauseSubscriptionAction::class)->execute($subscription->id, $tenant->id);
    }

    public function test_pause_fromPaused_throwsInvalidState(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);
        $paused = app(PauseSubscriptionAction::class)->execute($subscription->id, $tenant->id);

        $this->expectException(InvalidSubscriptionStateException::class);

        app(PauseSubscriptionAction::class)->execute($paused->id, $tenant->id);
    }

    public function test_pause_fromCancelled_throwsInvalidState(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);
        $cancelled = app(CancelSubscriptionAction::class)->execute($subscription->id, $tenant->id, immediate: true);

        $this->expectException(InvalidSubscriptionStateException::class);

        app(PauseSubscriptionAction::class)->execute($cancelled->id, $tenant->id);
    }

    public function test_resume_fromPaused_setsStatusActiveAndExtendsPeriod(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);
        $originalPeriodEnd = new DateTimeImmutable($subscription->currentPeriodEnd);

        $paused = app(PauseSubscriptionAction::class)->execute($subscription->id, $tenant->id);
        $resumed = app(ResumeSubscriptionAction::class)->execute($paused->id, $tenant->id);

        $this->assertSame('active', $resumed->status);
        $this->assertNull($resumed->pausedAt);
        $this->assertGreaterThanOrEqual($originalPeriodEnd, new DateTimeImmutable($resumed->currentPeriodEnd));
    }

    public function test_resume_fromActive_throwsInvalidState(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);

        $this->expectException(InvalidSubscriptionStateException::class);

        app(ResumeSubscriptionAction::class)->execute($subscription->id, $tenant->id);
    }

    public function test_cancel_immediate_setsStatusCancelledImmediately(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);

        $cancelled = app(CancelSubscriptionAction::class)->execute($subscription->id, $tenant->id, immediate: true);

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelledAt);
    }

    public function test_cancel_scheduled_setsFlagWithoutChangingStatus(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);

        $scheduled = app(CancelSubscriptionAction::class)->execute($subscription->id, $tenant->id, immediate: false);

        $this->assertTrue($scheduled->cancelAtPeriodEnd);
        $this->assertSame('active', $scheduled->status);
        $this->assertNull($scheduled->cancelledAt);
    }

    public function test_cancel_alreadyCancelled_throwsInvalidState(): void
    {
        $tenant = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenant->id, 5000);
        $cancelled = app(CancelSubscriptionAction::class)->execute($subscription->id, $tenant->id, immediate: true);

        $this->expectException(InvalidSubscriptionStateException::class);

        app(CancelSubscriptionAction::class)->execute($cancelled->id, $tenant->id, immediate: true);
    }

    public function test_upgrade_midPeriodWithRealPriceDifference_createsPaidProratedInvoiceAndChangesPlan(): void
    {
        $tenant = $this->createTenant();
        $oldPlan = $this->createPlan($tenant->id, 1000);
        $newPlan = $this->createPlan($tenant->id, 5000);
        $subscription = $this->createActiveSubscriptionForPlan($tenant->id, $oldPlan);

        $ps = new DateTimeImmutable($subscription->currentPeriodStart);
        $pe = new DateTimeImmutable($subscription->currentPeriodEnd);
        $totalDays = $ps->diff($pe)->days;

        $result = app(UpgradeSubscriptionAction::class)->execute($subscription->id, $tenant->id, $newPlan->id);

        // This Action runs the Upgrade essentially at currentPeriodStart
        // itself (the fixture is created and upgraded back-to-back).
        // `current_period_start`/`current_period_end` round-trip through
        // the DB (and through SubscriptionData's own DATE_ATOM formatting)
        // at whole-second precision, while `now` inside
        // SubscriptionProrationCalculator carries real sub-second
        // precision — so whether the elapsed fraction-of-a-second since
        // periodStart has crossed a whole-second boundary yet is
        // genuinely timing-dependent, not a defect in the Action. Both
        // "not yet crossed" (remainingDays == totalDays, the full price
        // difference) and "just crossed" (remainingDays == totalDays - 1,
        // one day short) are legitimate outcomes of this same formula —
        // assert the actual charge is one of the two, rather than a
        // hand-picked constant that's only correct half the time.
        $calculator = app(SubscriptionProrationCalculator::class);
        $fullCharge = $calculator->calculate(
            oldPrice: Money::fromAmount(1000, 'USD'),
            newPrice: Money::fromAmount(5000, 'USD'),
            periodStart: $ps,
            periodEnd: $pe,
            now: $ps,
        );
        $oneDayShortCharge = $calculator->calculate(
            oldPrice: Money::fromAmount(1000, 'USD'),
            newPrice: Money::fromAmount(5000, 'USD'),
            periodStart: $ps,
            periodEnd: $pe,
            now: $ps->modify('+1 second'),
        );

        $this->assertGreaterThan(27, $totalDays); // sanity: a real, non-trivial monthly billing period
        $this->assertNotNull($result['invoice']);
        $this->assertContains($result['invoice']->amount, [$fullCharge, $oneDayShortCharge]);
        $this->assertGreaterThan(0, $result['invoice']->amount);
        $this->assertSame('paid', $result['invoice']->status);
        $this->assertSame($newPlan->id, $result['subscription']->subscriptionPlanId);
    }

    public function test_upgrade_whenProratedAmountIsZero_createsNoInvoiceButStillChangesPlan(): void
    {
        $tenant = $this->createTenant();
        $oldPlan = $this->createPlan($tenant->id, 5000);
        $newPlan = $this->createPlan($tenant->id, 2000); // downgrade -> credit covers new cost -> 0
        $subscription = $this->createActiveSubscriptionForPlan($tenant->id, $oldPlan);

        $result = app(UpgradeSubscriptionAction::class)->execute($subscription->id, $tenant->id, $newPlan->id);

        $this->assertNull($result['invoice']);
        $this->assertSame($newPlan->id, $result['subscription']->subscriptionPlanId);
    }

    public function test_upgrade_whenGatewayDeclines_throwsPaymentFailedAndPlanDoesNotChange(): void
    {
        $tenant = $this->createTenant();
        $oldPlan = $this->createPlan($tenant->id, 1000);
        $newPlan = $this->createPlan($tenant->id, 5000);
        $subscription = $this->createActiveSubscriptionForPlan($tenant->id, $oldPlan);

        $this->app->instance(PaymentGatewayInterface::class, new class implements PaymentGatewayInterface {
            public function charge(Money $amount, PaymentMethod $method, array $paymentDetails): PaymentGatewayResult
            {
                return new PaymentGatewayResult(
                    successful: false,
                    transactionId: null,
                    rawResponse: ['error' => 'card_declined', 'message' => 'Declined by test double.'],
                );
            }
        });

        try {
            app(UpgradeSubscriptionAction::class)->execute($subscription->id, $tenant->id, $newPlan->id);
            $this->fail('Expected PaymentFailedException to be thrown.');
        } catch (PaymentFailedException $e) {
            // expected
        }

        $unchanged = app(SubscriptionRepositoryInterface::class)->findById($subscription->id, $tenant->id);
        $this->assertSame($oldPlan->id, $unchanged->subscriptionPlanId());
    }

    public function test_allLifecycleActions_nonexistentSubscription_throwSubscriptionNotFound(): void
    {
        $tenant = $this->createTenant();
        $plan = $this->createPlan($tenant->id, 5000);

        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(PauseSubscriptionAction::class)->execute(999999, $tenant->id)
        );
        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(ResumeSubscriptionAction::class)->execute(999999, $tenant->id)
        );
        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(CancelSubscriptionAction::class)->execute(999999, $tenant->id)
        );
        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(UpgradeSubscriptionAction::class)->execute(999999, $tenant->id, $plan->id)
        );
    }

    public function test_allLifecycleActions_subscriptionBelongingToDifferentTenant_throwSubscriptionNotFound(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $subscription = $this->createActiveSubscription($tenantA->id, 5000);
        $planB = $this->createPlan($tenantB->id, 8000);

        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(PauseSubscriptionAction::class)->execute($subscription->id, $tenantB->id)
        );
        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(ResumeSubscriptionAction::class)->execute($subscription->id, $tenantB->id)
        );
        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(CancelSubscriptionAction::class)->execute($subscription->id, $tenantB->id)
        );
        $this->assertActionThrows(
            SubscriptionNotFoundException::class,
            fn () => app(UpgradeSubscriptionAction::class)->execute($subscription->id, $tenantB->id, $planB->id)
        );
    }

    private function assertActionThrows(string $expectedException, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected {$expectedException} to be thrown.");
        } catch (Throwable $e) {
            $this->assertInstanceOf($expectedException, $e);
        }
    }

    private function createTenant(): TenantData
    {
        return app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
    }

    private function createPlan(int $tenantId, int $priceAmount, int $trialDays = 0): SubscriptionPlanData
    {
        return app(CreateSubscriptionPlanAction::class)->execute(
            tenantId: $tenantId,
            name: 'Plan '.uniqid(),
            description: null,
            billingCycle: 'monthly',
            priceAmount: $priceAmount,
            priceCurrency: 'USD',
            trialDays: $trialDays,
        );
    }

    private function createCustomer(int $tenantId): int
    {
        $customer = app(CreateCustomerAction::class)->execute(
            tenantId: $tenantId,
            firstName: 'Ada',
            lastName: 'Lovelace',
            email: 'ada+'.uniqid().'@example.test',
        );

        return $customer->id;
    }

    private function createActiveSubscription(int $tenantId, int $priceAmount): SubscriptionData
    {
        $plan = $this->createPlan($tenantId, $priceAmount);

        return $this->createActiveSubscriptionForPlan($tenantId, $plan);
    }

    private function createActiveSubscriptionForPlan(int $tenantId, SubscriptionPlanData $plan): SubscriptionData
    {
        $customerId = $this->createCustomer($tenantId);

        // trialDays = 0 on the plan -> CreateSubscriptionAction takes the
        // startActive() branch and charges immediately (MockPaymentGateway
        // succeeds by default), so the Subscription comes back Active.
        return app(CreateSubscriptionAction::class)->execute(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $plan->id,
            paymentMethodId: 'pm_test_'.uniqid(),
        );
    }

    private function createTrialSubscription(int $tenantId, int $priceAmount): SubscriptionData
    {
        $plan = $this->createPlan($tenantId, $priceAmount, trialDays: 14);
        $customerId = $this->createCustomer($tenantId);

        return app(CreateSubscriptionAction::class)->execute(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $plan->id,
            paymentMethodId: 'pm_test_'.uniqid(),
        );
    }
}
