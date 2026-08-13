<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\WebhookDeliveryLogData;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookDeliveryLogRepositoryInterface;

final class ListWebhookDeliveriesAction
{
    public function __construct(
        private readonly WebhookDeliveryLogRepositoryInterface $deliveries,
    ) {
    }

    /**
     * @return list<WebhookDeliveryLogData>
     */
    public function execute(int $businessId): array
    {
        return array_values(array_map(
            fn ($log) => WebhookDeliveryLogData::fromEntity($log),
            $this->deliveries->findByBusinessId($businessId),
        ));
    }
}
