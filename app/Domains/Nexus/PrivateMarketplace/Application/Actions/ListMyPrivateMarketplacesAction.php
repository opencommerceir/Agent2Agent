<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\PrivateMarketplace\Application\DTOs\PrivateMarketplaceSummaryData;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;

final class ListMyPrivateMarketplacesAction
{
    public function __construct(
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
    ) {
    }

    /**
     * @return list<PrivateMarketplaceSummaryData>
     */
    public function execute(int $businessId): array
    {
        $owned = array_map(
            fn ($m) => new PrivateMarketplaceSummaryData($m->id(), $m->nameEn(), true),
            $this->marketplaces->findByOwnerBusinessId($businessId),
        );

        $memberships = array_map(function ($membership) {
            $marketplace = $this->marketplaces->findById($membership->privateMarketplaceId());

            return new PrivateMarketplaceSummaryData($marketplace->id(), $marketplace->nameEn(), false);
        }, $this->members->findActiveMembershipsForBusiness($businessId));

        return [...$owned, ...$memberships];
    }
}
