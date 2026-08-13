<?php

namespace App\Domains\Nexus\PrivateMarketplace\Application\Actions;

use App\Domains\Nexus\PrivateMarketplace\Domain\Repositories\PrivateMarketplaceListingRepositoryInterface;
use InvalidArgumentException;

final class RemoveListingAction
{
    public function __construct(
        private readonly PrivateMarketplaceListingRepositoryInterface $listings,
    ) {
    }

    public function execute(int $listingId, int $callingBusinessId): void
    {
        $listing = $this->listings->findById($listingId);

        if (! $listing) {
            throw new InvalidArgumentException("Listing [{$listingId}] does not exist.");
        }

        if ($listing->listingBusinessId() !== $callingBusinessId) {
            throw new InvalidArgumentException('Only the Business that posted a listing may remove it.');
        }

        $this->listings->delete($listingId);
    }
}
