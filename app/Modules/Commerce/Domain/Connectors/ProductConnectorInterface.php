<?php

namespace App\Modules\Commerce\Domain\Connectors;

use App\Modules\Commerce\Domain\UCP\UCPProduct;

interface ProductConnectorInterface extends ConnectorInterface
{
    /**
     * @param array<string, mixed> $filters
     * @return list<UCPProduct>
     */
    public function getProducts(array $filters = []): array;

    public function getProduct(string $externalId): ?UCPProduct;
}
