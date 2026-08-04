<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionInvoiceData;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Domain\Events\SubscriptionPaymentFailed;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\PaymentMethod;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * The retry-path counterpart to `ProcessSubscriptionRenewalAction` — same
 * charge-and-record shape (charge via `PaymentGatewayInterface`, record the
 * outcome onto a SubscriptionInvoice), but operates on an *existing* Failed
 * invoice instead of creating a new one. Called by
 * `RetryFailedSubscriptionPaymentJob`, itself dispatched once per invoice
 * `SubscriptionInvoiceRepositoryInterface::findDueForRetry()` returns (rule
 * §د.5: "3 retries, 3-day spacing").
 *
 * Only this path — never the very-first-charge path in
 * `CreateSubscriptionAction`, nor the normal-renewal-failure path in
 * `ProcessSubscriptionRenewalAction` — is the one place `markPastDue()`
 * actually gets reached in practice, since it's the only path where
 * `hasExhaustedRetries()` (>= 3 failures on the same invoice) can become
 * true.
 */
final class RetrySubscriptionInvoicePaymentAction
{
    public function __construct(
        private readonly SubscriptionInvoiceRepositoryInterface $invoices,
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly PaymentGatewayInterface $gateway,
    ) {
    }

    public function execute(int $invoiceId, int $tenantId): ?SubscriptionInvoiceData
    {
        return DB::transaction(function () use ($invoiceId, $tenantId) {
            $invoice = $this->invoices->findById($invoiceId, $tenantId);

            if (! $invoice) {
                // This Action is only ever called from a Job with an id that
                // came straight out of the invoice repository's own
                // findDueForRetry() query — a not-found here means it was
                // deleted between query and job execution, not a
                // retryable/caller-facing condition. Mirrors
                // ProcessBulkImportJob's own "operation not found -> return,
                // not retryable" precedent.
                return null;
            }

            $subscription = $this->subscriptions->findById($invoice->subscriptionId(), $tenantId);

            if (! $subscription) {
                // Same reasoning as above — stale queued data, not retryable.
                return null;
            }

            $result = $this->gateway->charge(
                $invoice->amount(),
                PaymentMethod::CreditCard,
                ['payment_method_id' => $subscription->paymentMethodId()],
            );

            if ($result->successful) {
                $invoice->markPaid();
                $invoice = $this->invoices->save($invoice);

                if ($subscription->status() === SubscriptionStatus::PastDue) {
                    $subscription->reactivate();
                    $this->subscriptions->save($subscription);
                }

                // No event dispatch on a mere retry-recovery — this
                // codebase's own established restraint: not every
                // transition needs an event (e.g. pause/resume don't
                // dispatch one either).
                return SubscriptionInvoiceData::fromEntity($invoice);
            }

            $invoice->markFailed();
            $invoice = $this->invoices->save($invoice);

            Event::dispatch(new SubscriptionPaymentFailed($subscription, $invoice));

            if ($invoice->hasExhaustedRetries()) {
                $subscription->markPastDue();
                $this->subscriptions->save($subscription);
            }

            return SubscriptionInvoiceData::fromEntity($invoice);
        });
    }
}
