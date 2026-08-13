<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\Repositories;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplaceListing;

interface PrivateMarketplaceListingRepositoryInterface
{
    public function findById(int $id): ?PrivateMarketplaceListing;

    /**
     * @return list<PrivateMarketplaceListing>
     */
    public function findByPrivateMarketplaceId(int $privateMarketplaceId): array;

    public function save(PrivateMarketplaceListing $listing): PrivateMarketplaceListing;

    public function delete(int $id): void;
}
