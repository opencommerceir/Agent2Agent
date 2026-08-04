<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;

/**
 * A single-entity mutation with no cross-aggregate side effect — no
 * DB::transaction, no event dispatch. Pause/Resume are not among the 4
 * requested Domain Events (SubscriptionWasCreated/Renewed/Cancelled,
 * SubscriptionPaymentFailed); inventing a 5th here would be scope creep.
 */
final class PauseSubscriptionAction
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

        $subscription->pause(); // throws InvalidSubscriptionStateException on an illegal transition

        $subscription = $this->subscriptions->save($subscription);

        return SubscriptionData::fromEntity($subscription);
    }
}
