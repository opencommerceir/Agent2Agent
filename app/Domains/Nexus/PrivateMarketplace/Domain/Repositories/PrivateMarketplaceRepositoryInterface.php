<?php

namespace App\Domains\Nexus\PrivateMarketplace\Domain\Repositories;

use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplace;

interface PrivateMarketplaceRepositoryInterface
{
    public function findById(int $id): ?PrivateMarketplace;

    /**
     * @return list<PrivateMarketplace>
     */
    public function findByOwnerBusinessId(int $ownerBusinessId): array;

    public function save(PrivateMarketplace $marketplace): PrivateMarketplace;
}
