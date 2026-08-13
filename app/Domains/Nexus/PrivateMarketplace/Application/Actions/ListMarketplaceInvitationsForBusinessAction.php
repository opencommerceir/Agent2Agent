<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceMemberRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;

final class ListMarketplaceInvitationsForBusinessAction
{
    public function __construct(
        private readonly PrivateMarketplaceMemberRepositoryInterface $members,
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
    ) {
    }

    /**
     * @return list<array{memberId: int, marketplaceId: int, marketplaceNameEn: string}>
     */
    public function execute(int $businessId): array
    {
        return array_map(function ($invitation) {
            $marketplace = $this->marketplaces->findById($invitation->privateMarketplaceId());

            return [
                'memberId' => $invitation->id(),
                'marketplaceId' => $marketplace->id(),
                'marketplaceNameEn' => $marketplace->nameEn(),
            ];
        }, $this->members->findInvitationsForBusiness($businessId));
    }
}
