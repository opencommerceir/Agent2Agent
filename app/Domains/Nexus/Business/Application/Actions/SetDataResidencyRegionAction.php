<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\ValueObjects\DataResidencyRegion;
use InvalidArgumentException;

/**
 * A single-purpose Action, not folded into UpdateBusinessProfileAction —
 * same "one Action per concern, don't grow an existing Action's signature
 * for an unrelated field" reasoning Phase 1's UpdateCatalog split
 * (Product vs Service) documented. See DataResidencyRegion's own docblock
 * for what "setting" this actually means (a declared preference, not
 * infrastructure enforcement).
 */
final class SetDataResidencyRegionAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(int $businessId, DataResidencyRegion $region): BusinessData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $business->declareDataResidencyRegion($region);
        $business = $this->businesses->save($business);

        return BusinessData::fromEntity($business);
    }
}
