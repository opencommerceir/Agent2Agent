<?php

namespace App\Domains\Nexus\Developer\Domain\Repositories;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookSubscription;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;

interface WebhookSubscriptionRepositoryInterface
{
    public function findById(int $id): ?WebhookSubscription;

    /**
     * @return list<WebhookSubscription>
     */
    public function findByBusinessId(int $businessId): array;

    /**
     * @return list<WebhookSubscription>
     */
    public function findActiveByBusinessIdAndEvent(int $businessId, WebhookEvent $event): array;

    public function save(WebhookSubscription $subscription): WebhookSubscription;
}
