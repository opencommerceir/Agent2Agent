<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\WooCommerceSyncResult;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Entities\Product;
use App\Modules\Commerce\Domain\Events\ProductWasCreated;
use App\Modules\Commerce\Domain\Events\ProductWasUpdated;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\UCP\UCPProduct;
use App\Modules\Commerce\Domain\ValueObjects\Money;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;
use App\Modules\Commerce\Domain\ValueObjects\SKU;
use Exception;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Pulls a page of products from the 'woocommerce' Connector and upserts
 * each into this tenant's own Product catalog, keyed by SKU (the same
 * per-tenant identity CreateProductAction already enforces) — a second
 * sync with unchanged data updates the existing row in place rather than
 * duplicating it.
 *
 * Deliberately does not reuse CreateProductAction/UpdateProductAction:
 * those throw on "SKU already exists" / "product does not exist"
 * respectively, which is the wrong control flow for a bulk upsert that
 * must keep going and report per-item failures rather than abort the
 * whole page on the first bad SKU.
 */
final class SyncWooCommerceProductsAction
{
    public function __construct(
        private readonly ConnectorRegistry $connectors,
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    public function execute(int $tenantId, int $page = 1, int $limit = 20): WooCommerceSyncResult
    {
        $connector = $this->connectors->getProductConnector('woocommerce');

        $ucpProducts = $connector->getProducts(['page' => $page, 'per_page' => $limit]);

        $successCount = 0;
        $errors = [];

        foreach ($ucpProducts as $ucpProduct) {
            try {
                $this->upsert($tenantId, $ucpProduct);
                $successCount++;
            } catch (Exception $e) {
                $errors[] = "[{$ucpProduct->externalId}] {$e->getMessage()}";
            }
        }

        return new WooCommerceSyncResult(
            successCount: $successCount,
            failedCount: count($errors),
            errors: $errors,
        );
    }

    private function upsert(int $tenantId, UCPProduct $ucpProduct): void
    {
        $sku = new SKU($ucpProduct->sku); // throws InvalidSKUException on bad format

        $attributes = array_merge($ucpProduct->attributes, [
            'source_system' => $ucpProduct->sourceSystem,
            'external_id' => $ucpProduct->externalId,
        ]);

        $price = Money::fromAmount($ucpProduct->priceAmount, $ucpProduct->priceCurrency);
        $status = $ucpProduct->isAvailable ? ProductStatus::Active : ProductStatus::Draft;

        $existing = $this->products->findBySku($sku, $tenantId);

        if ($existing) {
            $existing->update(
                categoryId: $existing->categoryId(),
                name: $ucpProduct->name,
                description: $ucpProduct->description,
                price: $price,
                status: $status,
                attributes: $attributes,
            );

            $saved = $this->products->save($existing);

            Event::dispatch(new ProductWasUpdated($saved));

            return;
        }

        $product = Product::create(
            tenantId: $tenantId,
            categoryId: null,
            name: $ucpProduct->name,
            slug: Str::slug($ucpProduct->name),
            description: $ucpProduct->description,
            sku: $sku,
            price: $price,
            status: $status,
            attributes: $attributes,
        );

        $saved = $this->products->save($product);

        Event::dispatch(new ProductWasCreated($saved));
    }
}
