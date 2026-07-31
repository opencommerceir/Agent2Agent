<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShippingMethodData;
use App\Modules\Shipping\Domain\Entities\ShippingMethod;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Domain\ValueObjects\Money;

/**
 * One Action = one business operation: define a ShippingMethod. No
 * ShippingMethodWasCreated event — the request's own event list names
 * only 3, none for method creation (same "not every creation needs an
 * event" shape Loyalty's Reward/Workflows' Workflow have — HANDOFF §7.9).
 */
final class CreateShippingMethodAction
{
    public function __construct(
        private readonly ShippingMethodRepositoryInterface $methods,
    ) {
    }

    public function execute(
        int $tenantId,
        string $name,
        int $baseRate,
        int $ratePerKg,
        int $estimatedDaysMin,
        int $estimatedDaysMax,
        string $currency = 'USD',
        ?string $description = null,
    ): ShippingMethodData {
        $method = ShippingMethod::create(
            tenantId: $tenantId,
            name: $name,
            description: $description,
            baseRate: Money::fromAmount($baseRate, $currency),
            ratePerKg: Money::fromAmount($ratePerKg, $currency),
            estimatedDaysMin: $estimatedDaysMin,
            estimatedDaysMax: $estimatedDaysMax,
        );

        $method = $this->methods->save($method);

        return ShippingMethodData::fromEntity($method);
    }
}
