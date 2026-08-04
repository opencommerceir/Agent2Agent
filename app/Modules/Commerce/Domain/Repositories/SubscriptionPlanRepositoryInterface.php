<?php

namespace App\Modules\Commerce\Domain\Repositories;

use App\Modules\Commerce\Domain\Entities\SubscriptionPlan;

interface SubscriptionPlanRepositoryInterface
{
    public function findById(int $id, int $tenantId): ?SubscriptionPlan;

    /**
     * @return list<SubscriptionPlan>
     */
    public function listByTenant(int $tenantId, ?bool $isActive = null): array;

    public function save(SubscriptionPlan $plan): SubscriptionPlan;
}
