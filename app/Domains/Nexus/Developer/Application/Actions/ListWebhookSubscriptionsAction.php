<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\WebhookSubscriptionData;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;

final class ListWebhookSubscriptionsAction
{
    public function __construct(
        private readonly WebhookSubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    /**
     * @return list<WebhookSubscriptionData>
     */
    public function execute(int $businessId): array
    {
        return array_values(array_map(
            fn ($subscription) => WebhookSubscriptionData::fromEntity($subscription),
            $this->subscriptions->findByBusinessId($businessId),
        ));
    }
}
