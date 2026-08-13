<?php

namespace App\Domains\Nexus\Developer\Domain\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookDeliveryLog;

interface WebhookDeliveryLogRepositoryInterface
{
    /**
     * @return list<WebhookDeliveryLog>
     */
    public function findByBusinessId(int $businessId): array;

    public function save(WebhookDeliveryLog $log): WebhookDeliveryLog;
}
