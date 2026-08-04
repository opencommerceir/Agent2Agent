<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateSubscriptionPlanAction;
use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionInvoiceStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * End-to-end test for `subscription:process-due` (Phase 5, Stage 5, §7.25).
 * Both `ProcessDueSubscriptionsJob` and `RetryFailedSubscriptionPaymentJob`
 * run synchronously under this suite's `sync` queue driver (phpunit.xml),
 * so by the time the artisan call below returns, every due
 * Subscription/SubscriptionInvoice this run touched has already been fully
 * processed — the same CartAbandonedListenerTest/BulkPriceUpdateActionTest
 * convention.
 */
class ProcessDueSubscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dueSubscription_isRenewedByTheScheduledCommand(): void
    {
        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan('Acme Inc');

        $oldPeriodEnd = new DateTimeImmutable('-1 day');
        $subscription = Subscription::startActive(
            tenantId: $tenantId,
            customerId: $customerId,
            subscriptionPlanId: $planId,
            periodStart: new DateTimeImmutable('-1 month -1 day'),
            periodEnd: $oldPeriodEnd,
            paymentMethodId: 'pm_test_123',
        );
        $subscription = app(SubscriptionRepositoryInterface::class)->save($subscription);

        $this->artisan('subscription:process-due')->assertSuccessful();

        $reloaded = app(SubscriptionRepositoryInterface::class)->findById($subscription->id(), $tenantId);
        $this->assertGreaterThan($oldPeriodEnd, $reloaded->currentPeriodEnd());

        $invoices = app(SubscriptionInvoiceRepositoryInterface::class)->listBySubscription($subscription->id(), $tenantId);
        $this->assertCount(1, $invoices);
        $this->assertSame(SubscriptionInvoiceStatus::Paid, $invoices[0]->status());
    }

    public function test_dueRetryInvoice_isRetriedByTheScheduledCommand(): void
    {
        [$tenantId, $customerId, $planId] = $this->createTenantCustomerAndPlan('Acme Inc');

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

        $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), Money::fromAmount(2999, 'USD'), new DateTimeImmutable('-4 days'));
        $invoice->markFailed();
        $invoice = app(SubscriptionInvoiceRepositoryInterface::class)->save($invoice);

        // No `next_retry_at` column exists — isRetryDue() derives the 3-day
        // window from failed_at, so push failed_at back 4 real days to make
        // this invoice due for retry right now.
        DB::table('subscription_invoices')->where('id', $invoice->id())->update(['failed_at' => now()->subDays(4)]);

        $this->artisan('subscription:process-due')->assertSuccessful();

        $reloadedInvoice = app(SubscriptionInvoiceRepositoryInterface::class)->findById($invoice->id(), $tenantId);
        $this->assertSame(SubscriptionInvoiceStatus::Paid, $reloadedInvoice->status());
    }

    public function test_dueSubscriptionsAcrossTwoTenants_areBothProcessedWithoutCrossTenantLeakage(): void
    {
        [$tenantA, $customerA, $planA] = $this->createTenantCustomerAndPlan('Acme Inc');
        [$tenantB, $customerB, $planB] = $this->createTenantCustomerAndPlan('Beta Inc');

        $oldPeriodEndA = new DateTimeImmutable('-1 day');
        $subscriptionA = Subscription::startActive(
            tenantId: $tenantA,
            customerId: $customerA,
            subscriptionPlanId: $planA,
            periodStart: new DateTimeImmutable('-1 month -1 day'),
            periodEnd: $oldPeriodEndA,
            paymentMethodId: 'pm_test_a',
        );
        $subscriptionA = app(SubscriptionRepositoryInterface::class)->save($subscriptionA);

        $oldPeriodEndB = new DateTimeImmutable('-2 days');
        $subscriptionB = Subscription::startActive(
            tenantId: $tenantB,
            customerId: $customerB,
            subscriptionPlanId: $planB,
            periodStart: new DateTimeImmutable('-1 month -2 days'),
            periodEnd: $oldPeriodEndB,
            paymentMethodId: 'pm_test_b',
        );
        $subscriptionB = app(SubscriptionRepositoryInterface::class)->save($subscriptionB);

        $this->artisan('subscription:process-due')->assertSuccessful();

        $reloadedA = app(SubscriptionRepositoryInterface::class)->findById($subscriptionA->id(), $tenantA);
        $reloadedB = app(SubscriptionRepositoryInterface::class)->findById($subscriptionB->id(), $tenantB);

        $this->assertGreaterThan($oldPeriodEndA, $reloadedA->currentPeriodEnd());
        $this->assertGreaterThan($oldPeriodEndB, $reloadedB->currentPeriodEnd());

        // Each tenant's own invoice list contains exactly its own
        // Subscription's invoice, never the other tenant's.
        $invoicesA = app(SubscriptionInvoiceRepositoryInterface::class)->listBySubscription($subscriptionA->id(), $tenantA);
        $invoicesB = app(SubscriptionInvoiceRepositoryInterface::class)->listBySubscription($subscriptionB->id(), $tenantB);
        $this->assertCount(1, $invoicesA);
        $this->assertCount(1, $invoicesB);
        $this->assertSame($tenantA, $invoicesA[0]->tenantId());
        $this->assertSame($tenantB, $invoicesB[0]->tenantId());

        // Cross-tenant lookup finds nothing.
        $this->assertNull(app(SubscriptionRepositoryInterface::class)->findById($subscriptionA->id(), $tenantB));
        $this->assertNull(app(SubscriptionRepositoryInterface::class)->findById($subscriptionB->id(), $tenantA));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function createTenantCustomerAndPlan(string $tenantName): array
    {
        $tenant = app(CreateTenantAction::class)->execute($tenantName, strtolower(str_replace(' ', '-', $tenantName)).'-'.uniqid());
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
