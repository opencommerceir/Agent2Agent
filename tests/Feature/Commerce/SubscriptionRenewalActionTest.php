<?php

namespace Tests\Feature\Commerce;

use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateSubscriptionPlanAction;
use App\Modules\Commerce\Application\Actions\ProcessSubscriptionRenewalAction;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\PaymentGatewayResult;
use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Events\SubscriptionWasCancelled;
use App\Modules\Commerce\Domain\Events\SubscriptionWasRenewed;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionInvoiceStatus;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use App\Core\Application\Actions\CreateTenantAction;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Direct Action-level tests for ProcessSubscriptionRenewalAction (Phase 5,
 * Stage 5, §7.25) — the Action itself has no dedicated test yet
 * (CreateSubscriptionAction's own trial-path scenario exercises it
 * indirectly at best). Covers the 3 branches of its own docblock: a
 * successful renewal, a failed charge (Active, no PastDue — the renewal
 * grace period), and cancelAtPeriodEnd reaching its trigger with no charge
 * attempted at all.
 */
class SubscriptionRenewalActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfulRenewal_advancesPeriodAndMarksInvoicePaid(): void
    {
        Event::fake([SubscriptionWasRenewed::class]);

        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $oldPeriodStart = new DateTimeImmutable('-1 month');
        $oldPeriodEnd = new DateTimeImmutable('now');

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: $oldPeriodStart,
            periodEnd: $oldPeriodEnd,
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        app(ProcessSubscriptionRenewalAction::class)->execute($subscription->id(), $tenantId);

        $reloaded = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertSame(SubscriptionStatus::Active, $reloaded->status());
        $this->assertGreaterThan($oldPeriodEnd, $reloaded->currentPeriodEnd());
        $this->assertGreaterThan($oldPeriodStart, $reloaded->currentPeriodStart());

        $invoices = app(SubscriptionInvoiceRepositoryInterface::class)->listBySubscription($subscription->id(), $tenantId);
        $this->assertCount(1, $invoices);
        $this->assertSame(SubscriptionInvoiceStatus::Paid, $invoices[0]->status());

        Event::assertDispatched(SubscriptionWasRenewed::class);
    }

    public function test_failedRenewalCharge_staysActiveWithFailedInvoiceAndOneRetryCounted(): void
    {
        Event::fake([SubscriptionPaymentFailed::class]);
        $this->app->instance(PaymentGatewayInterface::class, $this->alwaysFailingGateway());

        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('now'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        app(ProcessSubscriptionRenewalAction::class)->execute($subscription->id(), $tenantId);

        $reloaded = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        // A renewal failure gets retry grace — it does NOT go PastDue on its
        // own first failure, unlike a brand-new Subscription's first charge.
        $this->assertSame(SubscriptionStatus::Active, $reloaded->status());

        $invoices = app(SubscriptionInvoiceRepositoryInterface::class)->listBySubscription($subscription->id(), $tenantId);
        $this->assertCount(1, $invoices);
        $this->assertSame(SubscriptionInvoiceStatus::Failed, $invoices[0]->status());
        $this->assertSame(1, $invoices[0]->retryCount());

        Event::assertDispatched(SubscriptionPaymentFailed::class);
    }

    public function test_cancelAtPeriodEndReached_transitionsToCancelledWithoutCreatingAnInvoice(): void
    {
        Event::fake([SubscriptionWasCancelled::class]);

        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan();

        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month'),
            periodEnd: new DateTimeImmutable('now'),
            paymentMethodId: 'pm_test_123',
        );
        $subscription->scheduleCancelAtPeriodEnd();
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);
        $this->assertTrue($subscription->cancelAtPeriodEnd());

        app(ProcessSubscriptionRenewalAction::class)->execute($subscription->id(), $tenantId);

        $reloaded = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertSame(SubscriptionStatus::Cancelled, $reloaded->status());
        $this->assertFalse($reloaded->cancelAtPeriodEnd());
        $this->assertNotNull($reloaded->cancelledAt());

        $invoices = app(SubscriptionInvoiceRepositoryInterface::class)->listBySubscription($subscription->id(), $tenantId);
        $this->assertCount(0, $invoices);

        Event::assertDispatched(SubscriptionWasCancelled::class);
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
