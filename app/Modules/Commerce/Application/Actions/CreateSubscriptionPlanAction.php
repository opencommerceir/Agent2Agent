<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionPlanData;
use App\Modules\Commerce\Domain\Entities\SubscriptionPlan;
use App\Modules\Commerce\Domain\Repositories\SubscriptionPlanRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\BillingCycle;
use App\Modules\Commerce\Domain\ValueObjects\Money;

final class CreateSubscriptionPlanAction
{
    public function __construct(
        private readonly SubscriptionPlanRepositoryInterface $plans,
    ) {
    }

    /**
     * @param array<int, string> $features
     */
    public function execute(
        int $tenantId,
        string $name,
        ?string $description,
        string $billingCycle,
        int $priceAmount,
        string $priceCurrency,
        int $trialDays = 0,
        array $features = [],
        bool $isActive = true,
    ): SubscriptionPlanData {
        $plan = SubscriptionPlan::create(
            tenantId: $tenantId,
            name: $name,
            description: $description,
            billingCycle: BillingCycle::from($billingCycle),
            price: Money::fromAmount($priceAmount, $priceCurrency),
            trialDays: $trialDays,
            features: $features,
            isActive: $isActive,
        );
        $plan = $this->plans->save($plan);

        return SubscriptionPlanData::fromEntity($plan);
    }
}
