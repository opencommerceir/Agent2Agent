<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Core\Application\Services\CacheService;
use App\Modules\Commerce\Application\DTOs\ProductData;
use App\Modules\Commerce\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;

/**
 * Cached for 1 hour (Phase 4 Stage 8, Performance Optimization, §7.20) —
 * the reference example for CacheService's own key-format convention
 * (`commerce:product:{tenantId}:{id}:v1`), chosen because it's this
 * stage's own literal example. Tenant id is part of the key even though
 * the request's own example (`commerce:product:123:v1`) omitted it:
 * without it, one tenant's cached Product could be served back to a
 * different tenant that happens to guess/reuse the same numeric id — a
 * real cross-tenant leak this app has avoided in every other capability
 * (the same reasoning Analytics' own capabilities dropped a
 * caller-supplied `tenant_id` input entirely, §7.18). Invalidated by
 * UpdateProductAction/DeleteProductAction via CacheService::forget() on
 * the exact same key — this Action is the only place that *reads* it, so
 * there's exactly one key format to keep in sync.
 */
final class GetProductAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly CacheService $cache,
    ) {
    }

    public function execute(int $id, int $tenantId): ProductData
    {
        $product = $this->cache->get(
            $this->cacheKey($id, $tenantId),
            fn () => $this->products->findById($id, $tenantId),
            3600,
        );

        if (! $product) {
            throw new ProductNotFoundException("Product [{$id}] does not exist.");
        }

        return ProductData::fromEntity($product);
    }

    public function cacheKey(int $id, int $tenantId): string
    {
        return "commerce:product:{$tenantId}:{$id}:v1";
    }
}
