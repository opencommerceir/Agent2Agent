<?php

namespace App\Modules\Commerce\Application\Jobs;

use App\Modules\Commerce\Application\Actions\RetrySubscriptionInvoicePaymentAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued per due-for-retry SubscriptionInvoice by
 * `ProcessDueSubscriptionsCommand` (`subscription:process-due`, daily
 * scheduler). Same shape as `ProcessDueSubscriptionsJob` — primitive-only
 * constructor, dependencies method-injected into handle(), unexpected
 * failures swallowed and logged so one bad invoice never aborts the batch.
 */
final class RetryFailedSubscriptionPaymentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $tenantId,
    ) {
    }

    public function handle(RetrySubscriptionInvoicePaymentAction $retryPayment): void
    {
        try {
            $retryPayment->execute($this->invoiceId, $this->tenantId);
        } catch (Throwable $e) {
            Log::error('RetryFailedSubscriptionPaymentJob failed for SubscriptionInvoice ['.$this->invoiceId.'] tenant ['.$this->tenantId.']: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
