<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use InvalidArgumentException;

final class RevokeWebhookSubscriptionAction
{
    public function __construct(
        private readonly WebhookSubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    public function execute(int $subscriptionId, int $actingBusinessId): void
    {
        $subscription = $this->subscriptions->findById($subscriptionId);

        if (! $subscription || $subscription->businessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("WebhookSubscription [{$subscriptionId}] does not belong to Business [{$actingBusinessId}].");
        }

        $subscription->revoke();

        $this->subscriptions->save($subscription);
    }
}
