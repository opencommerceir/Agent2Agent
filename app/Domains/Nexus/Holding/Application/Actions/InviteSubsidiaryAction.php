<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Entities\HoldingSubsidiary;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\ValueObjects\HoldingStatus;
use InvalidArgumentException;

final class InviteSubsidiaryAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly HoldingRepositoryInterface $holdings,
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
    ) {
    }

    public function execute(int $holdingId, int $callingBusinessId, int $targetBusinessId): void
    {
        $holding = $this->holdings->findById($holdingId);

        if (! $holding) {
            throw new InvalidArgumentException("Holding [{$holdingId}] does not exist.");
        }

        if ($holding->parentBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the Holding\'s administering Business may invite subsidiaries.');
        }

        if ($holding->status() !== HoldingStatus::Active) {
            throw new InvalidArgumentException("Holding [{$holdingId}] is not active.");
        }

        if ($targetBusinessId === $holding->parentBusinessId()) {
            throw new InvalidArgumentException('A Holding cannot invite its own administering Business as a subsidiary.');
        }

        if (! $this->businesses->findById($targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] does not exist.");
        }

        if ($this->holdings->findByParentBusinessId($targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] administers its own Holding and cannot become a subsidiary — nesting is not supported.");
        }

        if ($this->subsidiaries->findActiveOrInvitedByBusinessId($targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] already belongs to a Holding.");
        }

        if ($this->subsidiaries->findByHoldingAndBusiness($holdingId, $targetBusinessId)) {
            throw new InvalidArgumentException("Business [{$targetBusinessId}] has already been invited to this Holding.");
        }

        $this->subsidiaries->save(HoldingSubsidiary::invite($holdingId, $targetBusinessId));
    }
}
