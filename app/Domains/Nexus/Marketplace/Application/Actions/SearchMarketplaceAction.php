<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Marketplace\Infrastructure\Queries\BusinessSearchQuery;

/**
 * "Discovery" — verified businesses (and their matching catalog items)
 * a calling Business's Agent can go negotiate with. Excludes the caller's
 * own business; a Business cannot discover or negotiate with itself.
 */
final class SearchMarketplaceAction
{
    public function __construct(
        private readonly BusinessSearchQuery $businesses,
    ) {
    }

    /**
     * @return array{listings: array<int, array>}
     */
    public function execute(int $callingBusinessId, ?string $query = null, ?string $industry = null): array
    {
        $listings = $this->businesses->search($callingBusinessId, $query, $industry);

        return [
            'listings' => array_map(fn ($listing) => $listing->toArray(), $listings),
        ];
    }
}
