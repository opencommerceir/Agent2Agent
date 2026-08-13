<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\PrivateMarketplace\Application\DTOs\PrivateMarketplaceData;
use App\Domains\Nexus\PrivateMarketplace\Domain\Entities\PrivateMarketplace;
use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use InvalidArgumentException;

final class CreatePrivateMarketplaceAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
    ) {
    }

    public function execute(int $ownerBusinessId, string $nameFa, string $nameEn, ?string $brandingPrimaryColor = null): PrivateMarketplaceData
    {
        $owner = $this->businesses->findById($ownerBusinessId);

        if (! $owner) {
            throw new InvalidArgumentException("Business [{$ownerBusinessId}] does not exist.");
        }

        $marketplace = $this->marketplaces->save(PrivateMarketplace::create($ownerBusinessId, $nameFa, $nameEn, $brandingPrimaryColor));

        return PrivateMarketplaceData::fromEntity($marketplace, $owner, [], []);
    }
}
