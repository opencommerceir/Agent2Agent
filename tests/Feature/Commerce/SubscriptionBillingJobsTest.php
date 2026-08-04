<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateSubscriptionPlanAction;
use App\Modules\Commerce\Application\Actions\RetrySubscriptionInvoicePaymentAction;
use App\Modules\Commerce\Application\Jobs\ProcessDueSubscriptionsJob;
use App\Modules\Commerce\Application\Jobs\RetryFailedSubscriptionPaymentJob;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\PaymentGatewayResult;
use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionInvoiceStatus;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Direct Action/Job-level tests for the retry mechanism
 * (RetrySubscriptionInvoicePaymentAction, ProcessDueSubscriptionsJob,
 * RetryFailedSubscriptionPaymentJob — Phase 5, Stage 5, §7.25). Both Jobs
 * run synchronously under this suite's `sync` queue driver (phpunit.xml),
 * so dispatching either observes its real, final effect immediately — the
 * same ProcessBulkImportJob convention BulkPriceUpdateActionTest already
 * relies on.
 *
 * The 3-failures-in-a-row exhaustion scenario calls
 * RetrySubscriptionInvoicePaymentAction::execute() directly, repeatedly,
 * against a rebound always-failing gateway — the due-check
 * (SubscriptionInvoice::isRetryDue()) lives in the repository query, not in
 * the Action itself, so calling the Action directly bypasses it entirely,
 * exactly as this stage's own brief allows.
 */
class SubscriptionBillingJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_retryThatSucceeds_marksInvoicePaidAndReactivatesAPastDueSubscription(): void
    {
        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('+1 month'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription->markPastDue();
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), Money::fromAmount(2999, 'USD'), new DateTimeImmutable());
        $invoice->markFailed();
        $invoice = app(SubscriptionInvoiceRepositoryInterface::class)->save($invoice);

        // Default MockPaymentGateway binding succeeds (no simulate_failure flag).
        RetryFailedSubscriptionPaymentJob::dispatch($invoice->id(), $tenantId);

        $reloadedInvoice = app(SubscriptionInvoiceRepositoryInterface::class)->findById($invoice->id(), $tenantId);
        $this->assertSame(SubscriptionInvoiceStatus::Paid, $reloadedInvoice->status());

        $reloadedSubscription = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertSame(SubscriptionStatus::Active, $reloadedSubscription->status());
    }

    public function test_retryThatFailsButHasNotExhausted_incrementsRetryCountAndLeavesSubscriptionUnchanged(): void
    {
        Event::fake([SubscriptionPaymentFailed::class]);
        $this->app->instance(PaymentGatewayInterface::class, $this->alwaysFailingGateway());

        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('+1 month'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), Money::fromAmount(2999, 'USD'), new DateTimeImmutable());
        $invoice->markFailed(); // retryCount = 1 already, simulating one prior failure
        $invoice = app(SubscriptionInvoiceRepositoryInterface::class)->save($invoice);

        app(RetrySubscriptionInvoicePaymentAction::class)->execute($invoice->id(), $tenantId);

        $reloadedInvoice = app(SubscriptionInvoiceRepositoryInterface::class)->findById($invoice->id(), $tenantId);
        $this->assertSame(SubscriptionInvoiceStatus::Failed, $reloadedInvoice->status());
        $this->assertSame(2, $reloadedInvoice->retryCount());
        $this->assertFalse($reloadedInvoice->hasExhaustedRetries());

        $reloadedSubscription = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertSame(SubscriptionStatus::Active, $reloadedSubscription->status());

        Event::assertDispatched(SubscriptionPaymentFailed::class);
    }

    public function test_retryThatReachesExhaustion_marksSubscriptionPastDueAndDispatchesEventEachTime(): void
    {
        Event::fake([SubscriptionPaymentFailed::class]);
        $this->app->instance(PaymentGatewayInterface::class, $this->alwaysFailingGateway());

        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('+1 month'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), Money::fromAmount(2999, 'USD'), new DateTimeImmutable());
        $invoice->markFailed(); // 1st failure (the original renewal failure), retryCount = 1
        $invoice = app(SubscriptionInvoiceRepositoryInterface::class)->save($invoice);

        // 2nd failure (1st retry) — not exhausted yet (retryCount = 2).
        app(RetrySubscriptionInvoicePaymentAction::class)->execute($invoice->id(), $tenantId);
        $afterSecondFailure = app(SubscriptionInvoiceRepositoryInterface::class)->findById($invoice->id(), $tenantId);
        $this->assertSame(2, $afterSecondFailure->retryCount());
        $this->assertFalse($afterSecondFailure->hasExhaustedRetries());
        $this->assertSame(
            SubscriptionStatus::Active,
            app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId)->status(),
        );

        // 3rd failure (2nd retry) — exhausts retries, Subscription -> PastDue.
        app(RetrySubscriptionInvoicePaymentAction::class)->execute($invoice->id(), $tenantId);

        $finalInvoice = app(SubscriptionInvoiceRepositoryInterface::class)->findById($invoice->id(), $tenantId);
        $this->assertSame(3, $finalInvoice->retryCount());
        $this->assertTrue($finalInvoice->hasExhaustedRetries());

        $finalSubscription = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertSame(SubscriptionStatus::PastDue, $finalSubscription->status());

        Event::assertDispatchedTimes(SubscriptionPaymentFailed::class, 2);
    }

    public function test_processDueSubscriptionsJob_delegatesToRenewalActionForGivenIds(): void
    {
        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $oldPeriodEnd = new DateTimeImmutable('now');
        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: $oldPeriodEnd,
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        ProcessDueSubscriptionsJob::dispatch($subscription->id(), $tenantId);

        $reloaded = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertGreaterThan($oldPeriodEnd, $reloaded->currentPeriodEnd());
    }

    private function alwaysFailingGateway(): PaymentGatewayInterface
    {
        return new class implements PaymentGatewayInterface {
            public function charge(Money $amount, PaymentMethod $method, array $paymentDetails): PaymentGatewayResult
            {
                return new PaymentGatewayResult(
                    successful: false,
                    transactionId: null,
                    rawResponse: ['error' => 'card_declined'],
                );
            }
        };
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function createTenantCustomerAndPlan(): array
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $customer = app(CreateCustomerAction::class)->execute($tenant->id, 'Ada', 'Lovelace', 'ada-'.uniqid().'@example.com');
        $plan = app(CreateSubscriptionPlanAction::class)->execute(
            tenantId: $tenant->id,
            name: 'Pro Plan',
            description: null,
            billingCycle: 'monthly',
            priceAmount: 2999,
            priceCurrency: 'USD',
            trialDays: 0,
        );

        return [$tenant->id, $customer->id, $plan->id];
    }
}
