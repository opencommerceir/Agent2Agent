<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\Entities\Warehouse;
use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;

/**
 * Pure, framework-free — picks the closest Warehouse able to fulfil a
 * requested quantity, given candidates the caller already fetched
 * (Phase 5, Stage 2 — Multi-warehouse Inventory, §7.22). Deliberately
 * split from FindNearestWarehouseAction the same way
 * WarehouseDistanceCalculator itself is pure: this class never touches a
 * Repository or the database — it only combines a `$candidates` array
 * already built by its caller with WarehouseDistanceCalculator's own
 * formula. Constructor-injects WarehouseDistanceCalculator only.
 */
final class NearestWarehouseFinder
{
    public function __construct(
        private readonly WarehouseDistanceCalculator $distanceCalculator,
    ) {
    }

    /**
     * @param list<array{warehouse: Warehouse, availableQuantity: int}> $candidates
     */
    public function find(array $candidates, WarehouseLocation $customerLocation, int $requiredQuantity): ?Warehouse
    {
        $nearest = null;
        $nearestDistance = null;

        foreach ($candidates as $candidate) {
            $warehouse = $candidate['warehouse'];

            if (! $warehouse->isActive() || $candidate['availableQuantity'] < $requiredQuantity) {
                continue;
            }

            $distance = $this->distanceCalculator->calculate($warehouse->location(), $customerLocation);

            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearest = $warehouse;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }
}
