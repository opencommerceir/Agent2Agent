<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\SubscriptionInvoice;
use DateTimeImmutable;

interface SubscriptionInvoiceRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?SubscriptionInvoice;

    /**
     * @return list<SubscriptionInvoice>
     */
    public function listBySubscription(int $subscriptionId, int $tenantId): array;

    /**
     * Every Failed invoice this tenant has whose own `isRetryDue($before)`
     * is true — same tenant-scoped-method-in-a-cross-tenant-loop shape
     * `SubscriptionRepositoryInterface::findDueForRenewal()` uses.
     *
     * @return list<SubscriptionInvoice>
     */
    public function findDueForRetry(int $tenantId, DateTimeImmutable $before, int $intervalDays = 3): array;

    public function save(SubscriptionInvoice $invoice): SubscriptionInvoice;
}
