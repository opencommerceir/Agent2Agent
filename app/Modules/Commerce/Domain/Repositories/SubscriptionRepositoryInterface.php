<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\Subscription;
use App\Modules\Commerce\Domain\ValueObjects\SubscriptionStatus;
use DateTimeImmutable;

interface SubscriptionRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?Subscription;

    /**
     * @return list<Subscription>
     */
    public function listByTenant(int $tenantId, ?SubscriptionStatus $status = null, ?int $customerId = null): array;

    /**
     * Every Trial/Active Subscription whose `currentPeriodEnd` is at or
     * before $before — the query `ProcessDueSubscriptionsCommand` runs per
     * tenant (the same tenant-scoped-method-called-in-a-cross-tenant-loop
     * shape `CartRepositoryInterface::findStaleActive()` already
     * establishes for `commerce:check-abandoned-carts`, not a cross-tenant
     * method on this interface itself).
     *
     * @return list<Subscription>
     */
    public function findDueForRenewal(int $tenantId, DateTimeImmutable $before): array;

    public function save(Subscription $subscription): Subscription;
}
