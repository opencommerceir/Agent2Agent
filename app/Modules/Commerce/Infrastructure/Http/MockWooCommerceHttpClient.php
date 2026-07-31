<?php

namespace App\Modules\Commerce\Infrastructure\Http;

use App\Modules\Commerce\Application\Services\WooCommerceClientInterface;
use App\Modules\Commerce\Domain\Exceptions\WooCommerceApiException;

/**
 * Stands in for a live WooCommerce store until real credentials exist to
 * test against honestly (same reasoning MockProductConnector/
 * MockPaymentGateway give). Returns the exact JSON shape WooCommerce's
 * REST API (`GET /wp-json/wc/v3/products`) actually responds with —
 * tests/Fixtures/woocommerce-products-response.json documents the same
 * two products for reference — so swapping this for the real
 * WooCommerceClient later requires no change to WooCommerceProductMapper
 * or anything above it.
 *
 * Failure is opt-in via simulateFailure(), the same "deliberate,
 * documented test-triggering convention" MockPaymentGateway's
 * `simulate_failure` flag established, so WooCommerceApiException's path
 * through SyncWooCommerceProductsAction/GetWooCommerceProductAction is
 * actually exercisable in tests without real network mocking.
 */
final class MockWooCommerceHttpClient implements WooCommerceClientInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $products;

    /**
     * @param list<array<string, mixed>>|null $products
     */
    public function __construct(
        private bool $simulateFailure = false,
        ?array $products = null,
    ) {
        $this->products = $products ?? self::defaultProducts();
    }

    public function simulateFailure(bool $shouldFail = true): void
    {
        $this->simulateFailure = $shouldFail;
    }

    public function getProducts(int $page = 1, int $perPage = 20): array
    {
        if ($this->simulateFailure) {
            throw new WooCommerceApiException('Simulated WooCommerce API failure.');
        }

        $offset = max(0, $page - 1) * $perPage;

        return array_slice($this->products, $offset, $perPage);
    }

    public function getProduct(string $externalId): ?array
    {
        if ($this->simulateFailure) {
            throw new WooCommerceApiException('Simulated WooCommerce API failure.');
        }

        foreach ($this->products as $product) {
            if ((string) ($product['id'] ?? '') === $externalId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function defaultProducts(): array
    {
        return [
            [
                'id' => 123,
                'name' => 'WooCommerce T-Shirt',
                'slug' => 'woo-tshirt',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '29.99',
                'regular_price' => '29.99',
                'description' => 'A beautiful t-shirt from WooCommerce',
                'short_description' => 'Premium cotton t-shirt',
                'sku' => 'WOO-TSHIRT-001',
                'stock_quantity' => 50,
                'manage_stock' => true,
                'categories' => [
                    ['id' => 9, 'name' => 'Clothing', 'slug' => 'clothing'],
                ],
                'images' => [
                    ['id' => 100, 'src' => 'https://example.com/tshirt.jpg'],
                ],
            ],
            [
                'id' => 124,
                'name' => 'WooCommerce Mug',
                'slug' => 'woo-mug',
                'type' => 'simple',
                'status' => 'publish',
                'price' => '14.99',
                'regular_price' => '14.99',
                'description' => 'A ceramic mug from WooCommerce',
                'short_description' => null,
                'sku' => 'WOO-MUG-001',
                'stock_quantity' => 100,
                'manage_stock' => true,
                'categories' => [
                    ['id' => 10, 'name' => 'Accessories', 'slug' => 'accessories'],
                ],
                'images' => [],
            ],
        ];
    }
}
