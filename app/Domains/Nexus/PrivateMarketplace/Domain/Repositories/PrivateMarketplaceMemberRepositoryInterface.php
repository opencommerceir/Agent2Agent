<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\Repositories;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceMember;

interface PrivateMarketplaceMemberRepositoryInterface
{
    public function findById(int $id): ?PrivateMarketplaceMember;

    /**
     * @return list<PrivateMarketplaceMember>
     */
    public function findByPrivateMarketplaceId(int $privateMarketplaceId): array;

    public function findByMarketplaceAndBusiness(int $privateMarketplaceId, int $businessId): ?PrivateMarketplaceMember;

    /**
     * @return list<PrivateMarketplaceMember>
     */
    public function findInvitationsForBusiness(int $businessId): array;

    /**
     * @return list<PrivateMarketplaceMember>
     */
    public function findActiveMembershipsForBusiness(int $businessId): array;

    public function save(PrivateMarketplaceMember $member): PrivateMarketplaceMember;
}
