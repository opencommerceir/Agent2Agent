<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;

/**
 * A single-entity mutation with no cross-aggregate side effect — no
 * DB::transaction, no event dispatch (see PauseSubscriptionAction's own
 * docblock for why). Subscription::resume() itself extends
 * `currentPeriodEnd` by the pause duration; this Action just drives the
 * transition and persists it.
 */
final class ResumeSubscriptionAction
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

        $subscription->resume(); // throws InvalidSubscriptionStateException on an illegal transition

        $subscription = $this->subscriptions->save($subscription);

        return SubscriptionData::fromEntity($subscription);
    }
}
