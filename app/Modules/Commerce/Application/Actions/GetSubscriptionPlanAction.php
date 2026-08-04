<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionPlanData;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionPlanNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;

final class GetSubscriptionPlanAction
{
    public function __construct(
        private readonly SubscriptionPlanRepositoryInterface $plans,
    ) {
    }

    public function execute(int $id, int $tenantId): SubscriptionPlanData
    {
        $plan = $this->plans->findById($id, $tenantId);

        if (! $plan) {
            throw new SubscriptionPlanNotFoundException("SubscriptionPlan [{$id}] does not exist.");
        }

        return SubscriptionPlanData::fromEntity($plan);
    }
}
