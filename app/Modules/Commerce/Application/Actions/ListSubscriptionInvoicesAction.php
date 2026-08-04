<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionInvoiceData;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionInvoiceRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;

/**
 * A "missing piece implied by the request" addition (HANDOFF §3 pattern
 * #12) — `commerce.invoice.list` was requested but no dedicated Action
 * backing it was named; `SubscriptionInvoiceRepositoryInterface::listBySubscription()`
 * already existed, this is just the thin Action wrapper every other
 * capability already has one of. Confirms the Subscription itself belongs
 * to the tenant first (404 if not) before listing its invoices — the same
 * tenant-isolation-by-parent-lookup shape `ListProductVariantsAction`
 * already uses relative to its own parent Product.
 */
final class ListSubscriptionInvoicesAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly SubscriptionInvoiceRepositoryInterface $invoices,
    ) {
    }

    /**
     * @return list<SubscriptionInvoiceData>
     */
    public function execute(int $subscriptionId, int $tenantId): array
    {
        if (! $this->subscriptions->findById($subscriptionId, $tenantId)) {
            throw new SubscriptionNotFoundException("Subscription [{$subscriptionId}] does not exist.");
        }

        return array_map(
            fn ($invoice) => SubscriptionInvoiceData::fromEntity($invoice),
            $this->invoices->listBySubscription($subscriptionId, $tenantId),
        );
    }
}
