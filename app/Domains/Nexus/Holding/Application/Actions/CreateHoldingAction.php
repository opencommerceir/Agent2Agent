<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Holding\Application\DTOs\HoldingData;
use App\Domains\Nexus\Holding\Domain\Entities\Holding;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use InvalidArgumentException;

final class CreateHoldingAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
    ) {
    }

    public function execute(int $parentBusinessId, string $nameFa, string $nameEn): HoldingData
    {
        $parent = $this->businesses->findById($parentBusinessId);

        if (! $parent) {
            throw new InvalidArgumentException("Business [{$parentBusinessId}] does not exist.");
        }

        if ($this->holdings->findByParentBusinessId($parentBusinessId)) {
            throw new InvalidArgumentException("Business [{$parentBusinessId}] already administers a Holding.");
        }

        if ($this->subsidiaries->findActiveOrInvitedByBusinessId($parentBusinessId)) {
            throw new InvalidArgumentException("Business [{$parentBusinessId}] already belongs to another Holding as a subsidiary.");
        }

        $holding = $this->holdings->save(Holding::create($parentBusinessId, $nameFa, $nameEn));

        return HoldingData::fromEntity($holding, $parent, [], []);
    }
}
