<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\SuspensionAppealData;
use App\Domains\Nexus\Business\Domain\Entities\SuspensionAppeal;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use InvalidArgumentException;

/**
 * `business.auth`-gated — only the suspended Business's own owner can
 * appeal it, and only while actually suspended (appealing a standing
 * that no longer exists is meaningless, not merely redundant).
 */
final class SubmitSuspensionAppealAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly SuspensionAppealRepositoryInterface $appeals,
    ) {
    }

    public function execute(int $businessId, string $message): SuspensionAppealData
    {
        $business = $this->businesses->findById($businessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        if ($business->isActive()) {
            throw new InvalidArgumentException("Business [{$businessId}] is not currently suspended.");
        }

        $appeal = SuspensionAppeal::submit($businessId, $message);

        return SuspensionAppealData::fromEntity($this->appeals->save($appeal));
    }
}
