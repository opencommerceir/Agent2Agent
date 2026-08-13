<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use InvalidArgumentException;

final class LeaveMarketplaceAction
{
    public function __construct(
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $memberId, int $callingBusinessId): void
    {
        $member = $this->members->findById($memberId);

        if (! $member) {
            throw new InvalidArgumentException("Member [{$memberId}] does not exist.");
        }

        if ($member->businessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the member Business itself may leave.');
        }

        $member->remove();

        $this->members->save($member);
    }
}
