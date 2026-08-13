<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Application\DTOs\PrivateMarketplaceData;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use InvalidArgumentException;

final class GetPrivateMarketplaceAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
    ) {
    }

    public function execute(int $marketplaceId): PrivateMarketplaceData
    {
        $marketplace = $this->marketplaces->findById($marketplaceId);

        if (! $marketplace) {
            throw new InvalidArgumentException("Private Marketplace [{$marketplaceId}] does not exist.");
        }

        $owner = $this->businesses->findById($marketplace->ownerBusinessId());
        $members = $this->members->findByPrivateMarketplaceId($marketplaceId);

        $memberBusinesses = [];
        foreach ($members as $member) {
            $memberBusinesses[$member->businessId()] = $this->businesses->findById($member->businessId());
        }

        return PrivateMarketplaceData::fromEntity($marketplace, $owner, $members, $memberBusinesses);
    }
}
