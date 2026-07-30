<?php

namespace App\Modules\Commerce\Infrastructure\Connectors;

use App\Modules\Commerce\Domain\Connectors\ProductConnectorInterface;
use App\Modules\Commerce\Domain\UCP\UCPProduct;

/**
 * Returns fixed in-memory sample data — no external API, no database.
 * Exists so the Connector -> UCP contract can be exercised end-to-end
 * before any real commerce platform integration exists (Phase 2).
 */
final class MockProductConnector implements ProductConnectorInterface
{
    /**
     * @var list<UCPProduct>
     */
    private array $products;

    public function __construct()
    {
        $this->products = [
            new UCPProduct(
                externalId: 'mock-1',
                sourceSystem: 'mock',
                sku: 'SKU-001',
                name: 'Sample T-Shirt',
                description: 'A soft cotton t-shirt.',
                priceAmount: 1999,
                priceCurrency: 'USD',
                categoryIds: ['apparel'],
            ),
            new UCPProduct(
                externalId: 'mock-2',
                sourceSystem: 'mock',
                sku: 'SKU-002',
                name: 'Sample Mug',
                description: 'A ceramic mug.',
                priceAmount: 1299,
                priceCurrency: 'USD',
                categoryIds: ['home'],
            ),
        ];
    }

    public function getName(): string
    {
        return 'mock';
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function getProducts(array $filters = []): array
    {
        return $this->products;
    }

    public function getProduct(string $externalId): ?UCPProduct
    {
        foreach ($this->products as $product) {
            if ($product->externalId === $externalId) {
                return $product;
            }
        }

        return null;
    }
}
