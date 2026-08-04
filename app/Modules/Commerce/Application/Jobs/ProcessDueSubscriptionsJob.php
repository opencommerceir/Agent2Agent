<?php

namespace App\Modules\Commerce\Application\Jobs;

use App\Modules\Commerce\Application\Actions\ProcessSubscriptionRenewalAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued per due Subscription by `ProcessDueSubscriptionsCommand`
 * (`subscription:process-due`, daily scheduler). Constructor takes only
 * primitive ids — a queued Job's constructor arguments are serialized onto
 * the queue, so `ProcessSubscriptionRenewalAction` (and everything it
 * depends on) is method-injected into handle() instead, the same
 * `ProcessBulkImportJob` convention.
 *
 * The try/catch here swallows and logs any unexpected Throwable rather than
 * letting a single Subscription's failure abort the batch — a queued Job
 * failing shouldn't be a fatal, unhandled exception in a batch scheduler
 * context. `ProcessSubscriptionRenewalAction` itself is not expected to
 * throw for an ordinary declined charge (that's PaymentGatewayResult's own
 * job to report) — only for genuinely exceptional conditions like a missing
 * Subscription/Plan, which would only happen from stale queued data.
 */
final class ProcessDueSubscriptionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $subscriptionId,
        public readonly int $tenantId,
    ) {
    }

    public function handle(ProcessSubscriptionRenewalAction $processRenewal): void
    {
        try {
            $processRenewal->execute($this->subscriptionId, $this->tenantId);
        } catch (Throwable $e) {
            Log::error('ProcessDueSubscriptionsJob failed for Subscription ['.$this->subscriptionId.'] tenant ['.$this->tenantId.']: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
