<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Events\SubscriptionWasCancelled;
use App\Modules\Commerce\Domain\Events\SubscriptionWasRenewed;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionPlanNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\Services\SubscriptionRenewalService;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The one entry point that actually bills a Subscription — called both by
 * `ProcessDueSubscriptionsJob` (the daily scheduler path) and by
 * `CreateSubscriptionAction` itself (Actions composing Actions, HANDOFF §3
 * pattern #3) for a plan with no trial, whose very first period must be
 * charged immediately rather than waiting for tomorrow's scheduler run.
 *
 * Charges directly through `PaymentGatewayInterface` (the same port
 * `ProcessPaymentAction` already uses) rather than a full
 * Cart -> Order -> Payment pipeline — a SubscriptionPlan is not a Product
 * with Inventory, so forcing it through that pipeline would mean either
 * inventing a fake catalog Product per plan or bypassing Inventory checks
 * awkwardly. `SubscriptionInvoice.orderId` stays null this stage — see
 * that entity's own docblock. `payment_method_id` (a stored token/reference,
 * not one of `PaymentMethod`'s own 4 cases) is passed through inside
 * `$paymentDetails` instead of a new gateway-interface parameter, the same
 * "free-form details bag" shape `ProcessPaymentAction` already hands the
 * gateway.
 *
 * DB::transaction wraps the charge the same way ProcessPaymentAction's own
 * does — correct only because MockPaymentGateway is synchronous/local; a
 * real gateway integration should charge *outside* the transaction (that
 * Action's own docblock carries the identical, still-open caveat, §8.10).
 *
 * If `cancelAtPeriodEnd` is set, this call never charges at all — it
 * simply turns the scheduled flag into a real Cancelled transition
 * (Subscription::cancelAtPeriodEndReached()).
 */
final class ProcessSubscriptionRenewalAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionPlanRepositoryInterface $plans,
        private readonly SubscriptionInvoiceRepositoryInterface $invoices,
        private readonly SubscriptionRenewalService $renewalService,
        private readonly PaymentGatewayInterface $gateway,
    ) {
    }

    public function execute(int $subscriptionId, int $tenantId): SubscriptionData
    {
        return DB::transaction(function () use ($subscriptionId, $tenantId) {
            $subscription = $this->subscriptions->findById($subscriptionId, $tenantId);

            if (! $subscription) {
                throw new SubscriptionNotFoundException("Subscription [{$subscriptionId}] does not exist.");
            }

            if ($subscription->cancelAtPeriodEnd()) {
                $subscription->cancelAtPeriodEndReached();
                $subscription = $this->subscriptions->save($subscription);
                Event::dispatch(new SubscriptionWasCancelled($subscription));

                return SubscriptionData::fromEntity($subscription);
            }

            $plan = $this->plans->findById($subscription->subscriptionPlanId(), $tenantId);

            if (! $plan) {
                throw new SubscriptionPlanNotFoundException("SubscriptionPlan [{$subscription->subscriptionPlanId()}] does not exist.");
            }

            $now = new DateTimeImmutable();
            $period = $this->renewalService->nextPeriod($subscription, $plan->billingCycle(), $now);

            $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), $plan->price(), $now);
            $invoice = $this->invoices->save($invoice);

            $result = $this->gateway->charge(
                $plan->price(),
                PaymentMethod::CreditCard,
                ['payment_method_id' => $subscription->paymentMethodId()],
            );

            if ($result->successful) {
                $invoice->markPaid();
                $this->invoices->save($invoice);

                $subscription->renew($period['start'], $period['end']);
                $subscription = $this->subscriptions->save($subscription);

                Event::dispatch(new SubscriptionWasRenewed($subscription));
            } else {
                // A freshly created SubscriptionInvoice's retryCount is
                // always 0 before this markFailed() call, so it can never
                // read as exhausted (>= 3) right after its own first
                // failure — Subscription only ever reaches PastDue once
                // RetrySubscriptionInvoicePaymentAction's own 3rd retry on
                // this same invoice is exhausted (rule §د.5's "3 retries,
                // 3-day spacing"). This branch stays Active, exactly the
                // grace period a renewal failure gets that a brand-new
                // Subscription's own first charge does not (contrast
                // CreateSubscriptionAction, which marks PastDue on a
                // single failure with no retry grace at all — see that
                // Action's own docblock for why the two policies differ).
                $invoice->markFailed();
                $invoice = $this->invoices->save($invoice);

                Event::dispatch(new SubscriptionPaymentFailed($subscription, $invoice));
            }

            return SubscriptionData::fromEntity($subscription);
        });
    }
}
