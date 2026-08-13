<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Holding\Application\DTOs\HoldingData;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use InvalidArgumentException;

final class GetHoldingAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
    ) {
    }

    public function execute(int $holdingId): HoldingData
    {
        $holding = $this->holdings->findById($holdingId);

        if (! $holding) {
            throw new InvalidArgumentException("Holding [{$holdingId}] does not exist.");
        }

        $parent = $this->businesses->findById($holding->parentBusinessId());
        $subsidiaries = $this->subsidiaries->findByHoldingId($holdingId);

        $subsidiaryBusinesses = [];
        foreach ($subsidiaries as $subsidiary) {
            $subsidiaryBusinesses[$subsidiary->businessId()] = $this->businesses->findById($subsidiary->businessId());
        }

        return HoldingData::fromEntity($holding, $parent, $subsidiaries, $subsidiaryBusinesses);
    }
}
