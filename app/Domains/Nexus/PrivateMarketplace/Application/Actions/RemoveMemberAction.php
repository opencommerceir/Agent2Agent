<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use InvalidArgumentException;

final class RemoveMemberAction
{
    public function __construct(
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $memberId, int $callingBusinessId): void
    {
        $member = $this->members->findById($memberId);

        if (! $member) {
            throw new InvalidArgumentException("Member [{$memberId}] does not exist.");
        }

        $marketplace = $this->marketplaces->findById($member->privateMarketplaceId());

        if (! $marketplace || $marketplace->ownerBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the owning Business may remove a member.');
        }

        $member->remove();

        $this->members->save($member);
    }
}
