<?php

namespace App\Modules\Commerce\Infrastructure\Connectors;

use App\Modules\Commerce\Application\Services\WooCommerceClientInterface;
use App\Modules\Commerce\Domain\Connectors\ProductConnectorInterface;
use App\Modules\Commerce\Domain\Exceptions\WooCommerceApiException;
use App\Modules\Commerce\Domain\Services\WooCommerceProductMapper;
use App\Modules\Commerce\Domain\UCP\UCPProduct;
use App\Modules\Commerce\Domain\ValueObjects\WooCommerceProductData;

/**
 * The real ProductConnectorInterface implementation for WooCommerce:
 * fetches raw payloads via WooCommerceClientInterface and translates them
 * to UCPProduct via WooCommerceProductMapper — pure communication +
 * translation, no business rules (Connector Conventions).
 *
 * Non-obvious: this connector's WooCommerceClientInterface is injected
 * once, when CommerceServiceProvider::boot() constructs it and hands it to
 * ConnectorRegistry — it is never re-resolved from the container after
 * that. Rebinding WooCommerceClientInterface in the container (e.g. from a
 * test's setUp()) has no effect on an already-registered connector
 * instance. To swap in MockWooCommerceHttpClient for a test, re-register
 * a new WooCommerceProductConnector into ConnectorRegistry directly
 * (ConnectorRegistry::registerProductConnector('woocommerce', ...)) —
 * the same call boot() itself makes, just with a different client.
 */
final class WooCommerceProductConnector implements ProductConnectorInterface
{
    public function __construct(
        private readonly WooCommerceClientInterface $client,
        private readonly WooCommerceProductMapper $mapper,
        private readonly string $currency = 'USD',
    ) {
    }

    public function getName(): string
    {
        return 'woocommerce';
    }

    public function isConnected(): bool
    {
        try {
            $this->client->getProducts(1, 1);

            return true;
        } catch (WooCommerceApiException) {
            return false;
        }
    }

    public function getProducts(array $filters = []): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $raw = $this->client->getProducts($page, $perPage);

        return array_map(
            fn (array $item) => $this->mapper->toUCP(WooCommerceProductData::fromArray($item), $this->currency),
            $raw,
        );
    }

    public function getProduct(string $externalId): ?UCPProduct
    {
        $raw = $this->client->getProduct($externalId);

        if ($raw === null) {
            return null;
        }

        return $this->mapper->toUCP(WooCommerceProductData::fromArray($raw), $this->currency);
    }
}
