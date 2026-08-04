<?php

namespace App\Modules\Commerce\Application\Actions;

use App\Modules\Commerce\Application\DTOs\SubscriptionData;
use App\Modules\Commerce\Domain\Events\SubscriptionWasCancelled;
use App\Modules\Commerce\Domain\Exceptions\SubscriptionNotFoundException;
use App\Modules\Commerce\Domain\Repositories\SubscriptionRepositoryInterface;
use Illuminate\Support\Facades\Event;

/**
 * Immediate vs scheduled cancellation are deliberately asymmetric here.
 * `cancelImmediately()` is a real terminal state-machine transition
 * happening right now — worth a SubscriptionWasCancelled event, same as
 * any other Subscription lifecycle Action dispatches on its own real
 * transition. `scheduleCancelAtPeriodEnd()` only flips the
 * `cancelAtPeriodEnd` flag (see Subscription's own docblock: "a flag, not
 * a status") — it is not a transition at all yet, so dispatching
 * SubscriptionWasCancelled here would announce a Cancelled status that
 * hasn't actually happened. The real transition + event for a scheduled
 * cancellation only fires later, when ProcessSubscriptionRenewalAction
 * reaches the period end and calls Subscription::cancelAtPeriodEndReached()
 * (already wired in the foundation) — not this Action's concern.
 */
final class CancelSubscriptionAction
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    public function execute(int $id, int $tenantId, bool $immediate = false): SubscriptionData
    {
        $subscription = $this->subscriptions->findById($id, $tenantId);

        if (! $subscription) {
            throw new SubscriptionNotFoundException("Subscription [{$id}] does not exist.");
        }

        if ($immediate) {
            $subscription->cancelImmediately(); // throws InvalidSubscriptionStateException on an illegal transition
            $subscription = $this->subscriptions->save($subscription);

            Event::dispatch(new SubscriptionWasCancelled($subscription));

            return SubscriptionData::fromEntity($subscription);
        }

        $subscription->scheduleCancelAtPeriodEnd(); // throws InvalidSubscriptionStateException on an illegal transition
        $subscription = $this->subscriptions->save($subscription);

        return SubscriptionData::fromEntity($subscription);
    }
}
