<?php

namespace App\Console\Commands;

use App\Core\Domain\Repositories\TenantRepositoryInterface;
use App\Modules\Commerce\Application\Jobs\ProcessDueSubscriptionsJob;
use App\Modules\Commerce\Application\Jobs\RetryFailedSubscriptionPaymentJob;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * Scheduled daily via routes/console.php (Phase 5, Stage 5, §7.25) — the
 * recurring billing engine's entry point. Mirrors
 * `MarkAbandonedCartsCommand`'s own "iterate every Tenant, call a
 * tenant-scoped repository method once per tenant" shape, doubled: once for
 * Subscriptions due for renewal (`findDueForRenewal`), once for
 * SubscriptionInvoices due for a retry (`findDueForRetry`). Each due
 * row is dispatched as its own Job rather than processed inline, so one
 * Subscription/invoice's failure can never abort the whole scan (see each
 * Job's own docblock for its try/catch-and-log wrapper).
 */
class ProcessDueSubscriptionsCommand extends Command
{
    protected $signature = 'subscription:process-due';

    protected $description = 'Process due Subscription renewals and retry failed SubscriptionInvoice payments';

    public function handle(
        TenantRepositoryInterface $tenants,
        SubscriptionRepositoryInterface $subscriptions,
        SubscriptionInvoiceRepositoryInterface $invoices,
    ): int {
        $now = new DateTimeImmutable();
        $renewalsQueued = 0;
        $retriesQueued = 0;

        foreach ($tenants->all() as $tenant) {
            foreach ($subscriptions->findDueForRenewal($tenant->id(), $now) as $subscription) {
                ProcessDueSubscriptionsJob::dispatch($subscription->id(), $tenant->id());
                $renewalsQueued++;
            }

            foreach ($invoices->findDueForRetry($tenant->id(), $now) as $invoice) {
                RetryFailedSubscriptionPaymentJob::dispatch($invoice->id(), $tenant->id());
                $retriesQueued++;
            }
        }

        $this->info("Queued {$renewalsQueued} renewal(s) and {$retriesQueued} retry attempt(s).");

        return self::SUCCESS;
    }
}
