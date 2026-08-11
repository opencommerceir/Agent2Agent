<?php

namespace App\Domains\Nexus\Catalog\Application\Actions;

use App\Domains\Nexus\Catalog\Application\DTOs\ProductData;
use App\Domains\Nexus\Catalog\Application\DTOs\ServiceData;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;

/**
 * Searches a single Business's own catalog — both Products and Services
 * together, since "catalog" spans both. No search infrastructure exists
 * to reuse (nothing else in the platform indexes free text), so this is
 * a plain `name_fa`/`name_en` LIKE query — good enough for one Business's
 * own catalog size; a dedicated search index is a later-phase concern if
 * ever needed (docs/nexus-roadmap.md's Marketplace-wide discovery, not
 * this).
 */
final class SearchCatalogAction
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    /**
     * @return array{products: list<ProductData>, services: list<ServiceData>}
     */
    public function execute(int $businessId, string $query): array
    {
        $products = $query === ''
            ? $this->products->findByBusinessId($businessId)
            : $this->products->search($businessId, $query);

        $services = $query === ''
            ? $this->services->findByBusinessId($businessId)
            : $this->services->search($businessId, $query);

        return [
            'products' => array_map(fn ($product) => ProductData::fromEntity($product), $products),
            'services' => array_map(fn ($service) => ServiceData::fromEntity($service), $services),
        ];
    }
}
