<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionPlanData;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;

final class ListSubscriptionPlansAction
{
    public function __construct(
        private readonly SubscriptionPlanRepositoryInterface $plans,
    ) {
    }

    /**
     * @return list<SubscriptionPlanData>
     */
    public function execute(int $tenantId, ?bool $isActive = null): array
    {
        return array_map(
            fn ($plan) => SubscriptionPlanData::fromEntity($plan),
            $this->plans->listByTenant($tenantId, $isActive),
        );
    }
}
