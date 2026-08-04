<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Application\DTOs\SubscriptionInvoiceData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use App\Modules\Commerce\Domain\Exceptions\PaymentFailedException;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionPlanNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\Services\SubscriptionProrationCalculator;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Documented scope simplification: the request's own lifecycle prose
 * describes an Upgrade as "create a new subscription", but this
 * implementation does an in-place plan swap on the *same* Subscription row
 * via Subscription::changePlan() rather than creating a second Subscription
 * entity. The given DB schema has no "previous_subscription_id"-style
 * linking column, and a single subscription_id is what SubscriptionInvoice
 * — and every other table referencing a Subscription — already assumes.
 * Modeling an Upgrade as a brand-new Subscription row would require either
 * a schema change out of scope for this slice, or an orphaned/duplicated
 * subscription_id somewhere. This is the same reasoning
 * Subscription::changePlan()'s own docblock already gives (HANDOFF §7.25);
 * this is a stated, intentional scope simplification, not an oversight.
 *
 * Everything happens inside one DB::transaction: a $0 prorated amount
 * (e.g. a downgrade, or an upgrade requested right at period end — see
 * SubscriptionProrationCalculator's own docblock) never creates an invoice
 * or touches the gateway at all — an invoice for a $0 charge is
 * meaningless, so the plan simply changes for free. A non-zero proration
 * that gets declined rolls back the *entire* transaction via the thrown
 * PaymentFailedException, so the Subscription is left exactly as it was —
 * no partial state where the plan changed but the proration was never
 * paid (mirrors ProcessPaymentAction's own "a failed charge never reaches
 * the point an Order exists" precedent).
 *
 * Deliberately does NOT dispatch SubscriptionPaymentFailed on a declined
 * proration charge, unlike CreateSubscriptionAction /
 * ProcessSubscriptionRenewalAction. That event is reserved for the async
 * renewal/retry billing cycle (the parallel slice of this same stage) —
 * an Upgrade's own declined charge is a synchronous, immediate-feedback
 * failure the caller sees directly via the thrown exception, not
 * something a background billing job needs to react to later.
 */
final class UpgradeSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionPlanRepositoryInterface $plans,
        private readonly SubscriptionInvoiceRepositoryInterface $invoices,
        private readonly SubscriptionProrationCalculator $calculator,
        private readonly PaymentGatewayInterface $gateway,
    ) {
    }

    /**
     * @return array{subscription: SubscriptionData, invoice: ?SubscriptionInvoiceData}
     */
    public function execute(int $id, int $tenantId, int $newSubscriptionPlanId): array
    {
        return DB::transaction(function () use ($id, $tenantId, $newSubscriptionPlanId) {
            $subscription = $this->subscriptions->findById($id, $tenantId);

            if (! $subscription) {
                throw new SubscriptionNotFoundException("Subscription [{$id}] does not exist.");
            }

            $oldPlan = $this->plans->findById($subscription->subscriptionPlanId(), $tenantId);

            if (! $oldPlan) {
                throw new SubscriptionPlanNotFoundException(
                    "SubscriptionPlan [{$subscription->subscriptionPlanId()}] does not exist."
                );
            }

            $newPlan = $this->plans->findById($newSubscriptionPlanId, $tenantId);

            if (! $newPlan) {
                throw new SubscriptionPlanNotFoundException("SubscriptionPlan [{$newSubscriptionPlanId}] does not exist.");
            }

            $proratedCents = $this->calculator->calculate(
                oldPrice: $oldPlan->price(),
                newPrice: $newPlan->price(),
                periodStart: $subscription->currentPeriodStart(),
                periodEnd: $subscription->currentPeriodEnd(),
                now: new DateTimeImmutable(),
            );

            if ($proratedCents === 0) {
                $subscription->changePlan($newSubscriptionPlanId);
                $subscription = $this->subscriptions->save($subscription);

                return [
                    'subscription' => SubscriptionData::fromEntity($subscription),
                    'invoice' => null,
                ];
            }

            $amount = Money::fromAmount($proratedCents, $newPlan->price()->currency());

            $invoice = SubscriptionInvoice::create($tenantId, $subscription->id(), $amount, new DateTimeImmutable());
            $invoice = $this->invoices->save($invoice);

            $result = $this->gateway->charge(
                $amount,
                PaymentMethod::CreditCard,
                ['payment_method_id' => $subscription->paymentMethodId()],
            );

            if (! $result->successful) {
                $invoice->markFailed();
                $this->invoices->save($invoice);

                throw new PaymentFailedException(
                    'Upgrade proration charge was declined: '.($result->rawResponse['message'] ?? 'no reason given by the gateway.')
                );
            }

            $invoice->markPaid();
            $invoice = $this->invoices->save($invoice);

            $subscription->changePlan($newSubscriptionPlanId);
            $subscription = $this->subscriptions->save($subscription);

            return [
                'subscription' => SubscriptionData::fromEntity($subscription),
                'invoice' => SubscriptionInvoiceData::fromEntity($invoice),
            ];
        });
    }
}
