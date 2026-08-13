<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Catalog\Application\Actions\SearchCatalogAction;
use App\Domains\Nexus\Developer\Domain\Exceptions\IntegrationConnectionRevokedException;
use App\Domains\Nexus\Developer\Domain\Exceptions\IntegrationSyncFailedException;
use App\Domains\Nexus\Developer\Domain\Repositories\IntegrationConnectionRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

/**
 * The real, working half of the Integration Marketplace (Phase 9/M6):
 * pushes the calling Business's own catalog (via the exact same
 * SearchCatalogAction, Phase 1/M4, the portal catalog page itself uses)
 * to whatever generic target URL the Business configured, with its own
 * field mapping applied. On-demand (a portal button), not scheduled — no
 * new cron entry needed for this milestone; a periodic auto-sync is real
 * future work, not something skipped by accident.
 */
final class SyncCatalogToIntegrationAction
{
    private readonly ClientInterface $http;

    public function __construct(
        private readonly IntegrationConnectionRepositoryInterface $connections,
        private readonly SearchCatalogAction $searchCatalog,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client(['timeout' => 15]);
    }

    /**
     * @return array{itemsSent: int, httpStatus: ?int}
     */
    public function execute(int $connectionId, int $actingBusinessId): array
    {
        $connection = $this->connections->findById($connectionId);

        if (! $connection || $connection->businessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("IntegrationConnection [{$connectionId}] does not belong to Business [{$actingBusinessId}].");
        }

        if ($connection->isRevoked()) {
            throw new IntegrationConnectionRevokedException("IntegrationConnection [{$connectionId}] is revoked.");
        }

        $catalog = $this->searchCatalog->execute($actingBusinessId, '');

        $items = [
            ...array_map(fn ($product) => $connection->mapItem($product->toArray()), $catalog['products']),
            ...array_map(fn ($service) => $connection->mapItem($service->toArray()), $catalog['services']),
        ];

        $headers = ['Content-Type' => 'application/json'];

        if ($connection->authToken() !== null) {
            $headers['Authorization'] = "Bearer {$connection->authToken()}";
        }

        try {
            $response = $this->http->request('POST', $connection->targetUrl(), [
                'json' => ['items' => $items],
                'headers' => $headers,
            ]);

            return ['itemsSent' => count($items), 'httpStatus' => $response->getStatusCode()];
        } catch (GuzzleException $e) {
            throw new IntegrationSyncFailedException("Sync to [{$connection->targetUrl()}] failed: {$e->getMessage()}", previous: $e);
        }
    }
}
