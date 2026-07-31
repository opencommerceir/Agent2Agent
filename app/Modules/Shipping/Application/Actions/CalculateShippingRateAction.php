<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Shipping\Application\DTOs\ShippingRateData;
use App\Modules\Shipping\Domain\Exceptions\ShippingMethodNotFoundException;
use App\Modules\Shipping\Domain\Repositories\ShippingMethodRepositoryInterface;
use App\Modules\Shipping\Domain\Services\ShippingRateCalculator;
use App\Modules\Shipping\Domain\ValueObjects\Weight;

/**
 * A pure preview, no side effects — the same "preview vs. durable apply"
 * split CalculatePricingAction/ApplyCouponAction already establish
 * (HANDOFF §3 item 4). Nothing is persisted; CreateShipmentAction is the
 * durable counterpart that actually charges a real Shipment this cost.
 */
final class CalculateShippingRateAction
{
    public function __construct(
        private readonly ShippingMethodRepositoryInterface $methods,
        private readonly ShippingRateCalculator $calculator,
    ) {
    }

    public function execute(int $tenantId, int $shippingMethodId, int $weightGrams): ShippingRateData
    {
        $method = $this->methods->findById($shippingMethodId, $tenantId);

        if (! $method) {
            throw new ShippingMethodNotFoundException("ShippingMethod [{$shippingMethodId}] does not exist.");
        }

        $rate = $this->calculator->calculate(
            $method->baseRate(),
            $method->ratePerKg(),
            new Weight($weightGrams),
            $method->estimatedDaysMin(),
            $method->estimatedDaysMax(),
        );

        return ShippingRateData::fromValueObject($rate);
    }
}
