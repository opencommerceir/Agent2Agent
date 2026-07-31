<?php

namespace Tests\Feature\Commerce;

use App\Core\Application\Actions\CreateTenantAction;
use App\Modules\Commerce\Application\Actions\SyncWooCommerceProductsAction;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\WooCommerceProductMapper;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use App\Modules\Commerce\Infrastructure\Connectors\WooCommerceProductConnector;
use App\Modules\Commerce\Infrastructure\Http\MockWooCommerceHttpClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises SyncWooCommerceProductsAction directly (not through MCP —
 * WooCommerceConnectorCapabilityTest covers the full HTTP path). Swaps the
 * 'woocommerce' Connector registered by CommerceServiceProvider::boot()
 * for one backed by MockWooCommerceHttpClient, since rebinding
 * WooCommerceClientInterface in the container after boot() has no effect
 * (see WooCommerceProductConnector's docblock).
 */
class SyncWooCommerceProductsTest extends TestCase
{
    use RefreshDatabase;

    private function useMockConnector(MockWooCommerceHttpClient $client): void
    {
        app(ConnectorRegistry::class)->registerProductConnector(
            'woocommerce',
            new WooCommerceProductConnector($client, new WooCommerceProductMapper(), 'USD'),
        );
    }

    public function test_execute_withNoExistingProducts_createsBothAsActiveProducts(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $this->useMockConnector(new MockWooCommerceHttpClient());

        $result = app(SyncWooCommerceProductsAction::class)->execute($tenant->id);

        $this->assertSame(2, $result->successCount);
        $this->assertSame(0, $result->failedCount);
        $this->assertSame([], $result->errors);

        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'WOO-TSHIRT-001',
            'price_amount' => 2999,
            'price_currency' => 'USD',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'sku' => 'WOO-MUG-001',
            'price_amount' => 1499,
        ]);
    }

    public function test_execute_calledTwice_updatesExistingProductsInPlaceRatherThanDuplicating(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $this->useMockConnector(new MockWooCommerceHttpClient());

        app(SyncWooCommerceProductsAction::class)->execute($tenant->id);
        $second = app(SyncWooCommerceProductsAction::class)->execute($tenant->id);

        $this->assertSame(2, $second->successCount);
        $this->assertDatabaseCount('products', 2);

        $product = app(ProductRepositoryInterface::class)->findBySku(new SKU('WOO-TSHIRT-001'), $tenant->id);
        $this->assertNotNull($product);
        $this->assertSame('woocommerce', $product->attributes()['source_system']);
    }

    public function test_execute_whenConnectorApiFails_reportsZeroSuccessAndDoesNotThrow(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $client = new MockWooCommerceHttpClient();
        $client->simulateFailure(true);
        $this->useMockConnector($client);

        $this->expectException(\App\Modules\Commerce\Domain\Exceptions\WooCommerceApiException::class);

        app(SyncWooCommerceProductsAction::class)->execute($tenant->id);
    }

    public function test_execute_withInvalidSkuInOneItem_countsItAsFailedButPersistsTheOther(): void
    {
        $tenant = app(CreateTenantAction::class)->execute('Acme Inc', 'acme-'.uniqid());
        $client = new MockWooCommerceHttpClient(products: [
            ['id' => 1, 'name' => 'Bad SKU Product', 'status' => 'publish', 'price' => '9.99', 'sku' => 'a b'],
            ['id' => 2, 'name' => 'Good Product', 'status' => 'publish', 'price' => '5.00', 'sku' => 'GOOD-SKU'],
        ]);
        $this->useMockConnector($client);

        $result = app(SyncWooCommerceProductsAction::class)->execute($tenant->id);

        $this->assertSame(1, $result->successCount);
        $this->assertSame(1, $result->failedCount);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('[1]', $result->errors[0]);
        $this->assertDatabaseHas('products', ['tenant_id' => $tenant->id, 'sku' => 'GOOD-SKU']);
    }
}
