<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\UCP\UCPProduct;

/**
 * A live, uncached lookup straight from the connected WooCommerce store —
 * distinct from `commerce.product.search`, which only ever queries this
 * platform's own already-synced catalog. Returns the UCP snapshot as-is;
 * it is never persisted here (UCPProduct's own docblock: "never
 * persisted, never touched by any Stage 1-5 work"). Reuses
 * ProductNotFoundException rather than introducing a WooCommerce-specific
 * one — it already implements Core's NotFoundExceptionInterface, so no
 * new marker-interface wiring is needed for this to map to 404.
 */
final class GetWooCommerceProductAction
{
    public function __construct(
        private readonly ConnectorRegistry $connectors,
    ) {
    }

    public function execute(string $externalId): UCPProduct
    {
        $connector = $this->connectors->getProductConnector('woocommerce');

        $product = $connector->getProduct($externalId);

        if ($product === null) {
            throw new ProductNotFoundException("WooCommerce product [{$externalId}] was not found.");
        }

        return $product;
    }
}
