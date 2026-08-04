<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Events\SubscriptionWasCreated;
use App\Modules\Commerce\Domain\Exceptions\CustomerNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionPlanNotFoundException;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\Services\SubscriptionBillingCalculator;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Rule §ه.1: a plan with a real trial (`trialDays > 0`) starts a
 * Subscription in Trial with no charge at all — the first real invoice
 * only ever happens once the trial ends, via a normal scheduled renewal
 * (`ProcessSubscriptionRenewalAction`, called by `ProcessDueSubscriptionsJob`
 * once `currentPeriodEnd` — set to `trialEnd` — is reached; this stage's
 * own literal end-to-end scenario exercises exactly this path).
 *
 * A plan with no trial charges immediately, and deliberately does **not**
 * reuse `ProcessSubscriptionRenewalAction` for it — rule §ه.1's own text
 * ("Generate first invoice → Process payment → If fail: status = past_due")
 * sends a single failed *first* charge straight to PastDue, no 3-retry
 * grace period at all, unlike a *renewal* failure (rule §ه.2), which does
 * get 3 retries before PastDue (`SubscriptionInvoice::hasExhaustedRetries()`).
 * Composing `ProcessSubscriptionRenewalAction` here would have silently
 * applied the wrong (lenient) failure policy to a first charge — so this
 * Action inlines its own charge-and-record step instead, small enough that
 * duplicating it is cheaper and clearer than forcing one shared method to
 * serve two genuinely different failure policies (the same
 * "small, Action-local logic over a shared method for something this
 * narrow" tradeoff `ProcessPaymentAction`'s own `resolveRuleDiscount()`
 * already accepts).
 */
final class CreateSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionPlanRepositoryInterface $plans,
        private readonly SubscriptionInvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly SubscriptionBillingCalculator $calculator,
        private readonly PaymentGatewayInterface $gateway,
    ) {
    }

    public function execute(
        int $tenantId,
        int $customerId,
        int $subscriptionPlanId,
        ?string $paymentMethodId = null,
    ): SubscriptionData {
        return DB::transaction(function () use ($tenantId, $customerId, $subscriptionPlanId, $paymentMethodId) {
            if (! $this->customers->findById($customerId, $tenantId)) {
                throw new CustomerNotFoundException("Customer [{$customerId}] does not exist.");
            }

            $plan = $this->plans->findById($subscriptionPlanId, $tenantId);

            if (! $plan) {
                throw new SubscriptionPlanNotFoundException("SubscriptionPlan [{$subscriptionPlanId}] does not exist.");
            }

            if ($plan->hasTrial()) {
                $subscription = Subscription::startTrial(
                    tenantId: $tenantId,
                    customerId: $customerId,
                    subscriptionPlanId: $subscriptionPlanId,
                    trialDays: $plan->trialPeriod()->days(),
                    paymentMethodId: $paymentMethodId,
                );
                $subscription = $this->subscriptions->save($subscription);

                Event::dispatch(new SubscriptionWasCreated($subscription));

                return SubscriptionData::fromEntity($subscription);
            }

            $now = new DateTimeImmutable();
            $periodEnd = $this->calculator->nextPeriodEnd($now, $plan->billingCycle());

            $subscription = Subscription::startActive(
                tenantId: $tenantId,
                customerId: $customerId,
                subscriptionPlanId: $subscriptionPlanId,
                periodStart: $now,
                periodEnd: $periodEnd,
                paymentMethodId: $paymentMethodId,
            );
            $subscription = $this->subscriptions->save($subscription);

            Event::dispatch(new SubscriptionWasCreated($subscription));

            $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), $plan->price(), $now);
            $invoice = $this->invoices->save($invoice);

            $result = $this->gateway->charge(
                $plan->price(),
                PaymentMethod::CreditCard,
                ['payment_method_id' => $paymentMethodId],
            );

            if ($result->successful) {
                $invoice->markPaid();
                $this->invoices->save($invoice);
            } else {
                $invoice->markFailed();
                $invoice = $this->invoices->save($invoice);

                Event::dispatch(new SubscriptionPaymentFailed($subscription, $invoice));

                $subscription->markPastDue();
                $subscription = $this->subscriptions->save($subscription);
            }

            return SubscriptionData::fromEntity($subscription);
        });
    }
}
