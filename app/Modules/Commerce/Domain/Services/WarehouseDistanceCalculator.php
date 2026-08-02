<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\ValueObjects\WarehouseLocation;

/**
 * Pure, framework-free — the single formula owner for "how far apart are
 * two points on Earth" (Phase 5, Stage 2 — Multi-warehouse Inventory,
 * §7.22), the same shape Shipping's own ShippingRateCalculator already
 * establishes for its module: no Repository dependency, only combines
 * values already handed to it.
 *
 * Uses the Haversine formula rather than a flat Euclidean/Pythagorean
 * approximation because WarehouseLocation stores latitude/longitude in
 * degrees on a sphere, not planar coordinates — at the distances a
 * multi-warehouse network spans (tens to thousands of km), ignoring
 * Earth's curvature produces meaningfully wrong results, especially at
 * higher latitudes where a degree of longitude covers less ground than a
 * degree of latitude. Haversine is the standard, well-understood
 * great-circle distance formula for exactly this class of problem and is
 * accurate enough for "which warehouse is nearest" without pulling in a
 * full geodesy library (Earth is treated as a perfect sphere, not the
 * true oblate spheroid — the resulting error is well under 1%, far
 * smaller than warehouse-to-customer distances warrant caring about).
 */
final class WarehouseDistanceCalculator
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function calculate(WarehouseLocation $a, WarehouseLocation $b): float
    {
        $latDelta = deg2rad($b->latitude - $a->latitude);
        $lonDelta = deg2rad($b->longitude - $a->longitude);

        $latA = deg2rad($a->latitude);
        $latB = deg2rad($b->latitude);

        $haversine = sin($latDelta / 2) ** 2
            + cos($latA) * cos($latB) * sin($lonDelta / 2) ** 2;

        $angularDistance = 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));

        return self::EARTH_RADIUS_KM * $angularDistance;
    }
}
