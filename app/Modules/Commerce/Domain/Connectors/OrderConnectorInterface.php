<?php

namespace App\Modules\Commerce\Domain\Connectors;

use App\Modules\Commerce\Domain\UCP\UCPOrder;

interface OrderConnectorInterface extends ConnectorInterface
{
    /**
     * @param array<string, mixed> $filters
     * @return list<UCPOrder>
     */
    public function getOrders(array $filters = []): array;

    public function getOrder(string $externalId): ?UCPOrder;
}
