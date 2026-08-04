<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;

final class GetSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    public function execute(int $id, int $tenantId): SubscriptionData
    {
        $subscription = $this->subscriptions->findById($id, $tenantId);

        if (! $subscription) {
            throw new SubscriptionNotFoundException("Subscription [{$id}] does not exist.");
        }

        return SubscriptionData::fromEntity($subscription);
    }
}
