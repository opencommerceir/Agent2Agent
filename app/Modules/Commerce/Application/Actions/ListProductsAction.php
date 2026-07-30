<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\ProductData;
use App\Modules\Commerce\Domain\Entities\Product;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\ValueObjects\ProductStatus;

/**
 * Backs the `commerce.product.search` MCP capability — takes the raw
 * `array $input` MCP Gateway received plus the authenticated Agent's
 * tenantId (CapabilityHandlerRegistry's `callable(array, int): array`
 * contract) and doubles directly as the callable
 * CommerceServiceProvider::boot() registers, the same
 * doubling-as-capability-handler pattern the Demo module established.
 *
 * Only ever searches Active products: an Agent discovering the catalog
 * through MCP has no business use for draft/archived products, and the
 * Repository itself must not encode that decision (Repository
 * Conventions — no business rules in Repositories), so it is applied
 * here instead.
 */
final class ListProductsAction
{
    private const DEFAULT_LIMIT = 20;

    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{products: list<array<string, mixed>>}
     */
    public function execute(array $input, int $tenantId): array
    {
        $query = isset($input['query']) && is_string($input['query']) && $input['query'] !== ''
            ? $input['query']
            : null;

        $limit = isset($input['limit']) && is_int($input['limit'])
            ? max(1, min($input['limit'], self::MAX_LIMIT))
            : self::DEFAULT_LIMIT;

        $products = $this->products->search($tenantId, $query, ProductStatus::Active, $limit, 0);

        return [
            'products' => array_map(
                fn (Product $product) => ProductData::fromEntity($product)->toArray(),
                $products,
            ),
        ];
    }
}
