<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\BusinessData;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Admin-only approval step. Dispatches BusinessWasVerified rather than
 * calling into the Agent domain directly — Inter-Module Communication
 * (docs/modules.md) is event-driven, never a direct cross-domain call.
 */
final class VerifyBusinessAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(int $businessId): BusinessData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $business->verify();
        $business = $this->businesses->save($business);

        Event::dispatch(new BusinessWasVerified($business));

        return BusinessData::fromEntity($business);
    }
}
