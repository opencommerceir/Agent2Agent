<?php

namespace App\Domains\Nexus\Business\Application\Actions;

use App\Domains\Nexus\Business\Application\DTOs\SuspensionAppealData;
use App\Domains\Nexus\Business\Domain\Repositories\SuspensionAppealRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — Approved reactivates the Business immediately (delegates to
 * ReactivateBusinessAction, the same single choke point every reactivation
 * goes through); Denied only closes the appeal, the Business stays
 * suspended.
 */
final class ResolveSuspensionAppealAction
{
    public function __construct(
        private readonly SuspensionAppealRepositoryInterface $appeals,
        private readonly ReactivateBusinessAction $reactivateBusiness,
    ) {
    }

    public function execute(int $appealId, bool $approve): SuspensionAppealData
    {
        $appeal = $this->appeals->findById($appealId);

        if (! $appeal) {
            throw new InvalidArgumentException("SuspensionAppeal [{$appealId}] does not exist.");
        }

        if ($approve) {
            $appeal->approve();
            $this->reactivateBusiness->execute($appeal->businessId(), "Appeal #{$appealId} approved.");
        } else {
            $appeal->deny();
        }

        return SuspensionAppealData::fromEntity($this->appeals->save($appeal));
    }
}
