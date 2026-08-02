<?php

namespace App\Modules\Shipping\Application\Actions;

use App\Modules\Commerce\Application\Actions\FindNearestWarehouseAction;
use App\Modules\Commerce\Domain\Services\WarehouseDistanceCalculator;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;
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
 *
 * Depends directly on Commerce's own FindNearestWarehouseAction/
 * WarehouseDistanceCalculator (Phase 5, Stage 2 — Multi-warehouse
 * Inventory, §7.22) — a plain, concrete, container-autowired class, the
 * same "reuse another module's read-only computation without an
 * Interface" shape Analytics' CalculateKPIAction already establishes for
 * Reporting's Query Builders. The four new trailing params are optional:
 * when any is omitted, behavior is byte-for-byte identical to before this
 * stage (no distance surcharge, no Commerce lookup at all).
 */
final class CalculateShippingRateAction
{
    public function __construct(
        private readonly ShippingMethodRepositoryInterface $methods,
        private readonly ShippingRateCalculator $calculator,
        private readonly FindNearestWarehouseAction $findNearestWarehouse,
        private readonly WarehouseDistanceCalculator $distanceCalculator,
    ) {
    }

    public function execute(
        int $tenantId,
        int $shippingMethodId,
        int $weightGrams,
        ?float $customerLatitude = null,
        ?float $customerLongitude = null,
        ?int $productId = null,
        ?int $requiredQuantity = null,
    ): ShippingRateData {
        $method = $this->methods->findById($shippingMethodId, $tenantId);

        if (! $method) {
            throw new ShippingMethodNotFoundException("ShippingMethod [{$shippingMethodId}] does not exist.");
        }

        $distanceKm = null;

        if ($customerLatitude !== null && $customerLongitude !== null && $productId !== null && $requiredQuantity !== null) {
            $customerLocation = new WarehouseLocation($customerLatitude, $customerLongitude, 'Customer Location');

            $nearestWarehouse = $this->findNearestWarehouse->execute(
                $tenantId,
                $productId,
                $customerLatitude,
                $customerLongitude,
                $requiredQuantity,
            );

            if ($nearestWarehouse) {
                $warehouseLocation = new WarehouseLocation(
                    $nearestWarehouse->location->latitude,
                    $nearestWarehouse->location->longitude,
                    $nearestWarehouse->location->address,
                );

                $distanceKm = $this->distanceCalculator->calculate($warehouseLocation, $customerLocation);
            }
        }

        $rate = $this->calculator->calculate(
            $method->baseRate(),
            $method->ratePerKg(),
            new Weight($weightGrams),
            $method->estimatedDaysMin(),
            $method->estimatedDaysMax(),
            $distanceKm,
            $distanceKm !== null ? $method->ratePerKm() : null,
        );

        return ShippingRateData::fromValueObject($rate);
    }
}
