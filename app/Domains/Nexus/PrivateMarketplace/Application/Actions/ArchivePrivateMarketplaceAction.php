<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceRepositoryInterface;
use InvalidArgumentException;

final class ArchivePrivateMarketplaceAction
{
    public function __construct(
        private readonly PrivateMarketplaceRepositoryInterface $marketplaces,
    ) {
    }

    public function execute(int $marketplaceId, int $callingBusinessId): void
    {
        $marketplace = $this->marketplaces->findById($marketplaceId);

        if (! $marketplace) {
            throw new InvalidArgumentException("Private Marketplace [{$marketplaceId}] does not exist.");
        }

        if ($marketplace->ownerBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the owning Business may archive this Private Marketplace.');
        }

        $marketplace->archive();

        $this->marketplaces->save($marketplace);
    }
}
