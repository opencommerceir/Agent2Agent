<?php

namespace App\Domains\Nexus\Holding\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Holding\Application\DTOs\HoldingInvitationData;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingRepositoryInterface;
use App\Domains\Nexus\Holding\Domain\Repositories\HoldingSubsidiaryRepositoryInterface;

final class ListHoldingInvitationsForBusinessAction
{
    public function __construct(
        private readonly HoldingSubsidiaryRepositoryInterface $subsidiaries,
        private readonly HoldingRepositoryInterface $holdings,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    /**
     * @return list<HoldingInvitationData>
     */
    public function execute(int $businessId): array
    {
        $invitations = $this->subsidiaries->findInvitationsForBusiness($businessId);

        return array_map(function ($invitation) {
            $holding = $this->holdings->findById($invitation->holdingId());
            $parent = $this->businesses->findById($holding->parentBusinessId());

            return new HoldingInvitationData(
                subsidiaryId: $invitation->id(),
                holdingId: $holding->id(),
                holdingNameEn: $holding->nameEn(),
                parentBusinessNameEn: $parent->nameEn(),
                invitedAt: $invitation->invitedAt()->format(DATE_ATOM),
            );
        }, $invitations);
    }
}
