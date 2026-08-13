<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Application\DTOs\WebhookSubscriptionData;
use App\Domains\Nexus\Developer\Domain\Entities\WebhookSubscription;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;

/**
 * Generates the signing secret exactly once — same one-time-reveal
 * contract IssueApiKeyAction (Phase 9/M1) already established for its own
 * plaintext key. Unlike ApiKey's secret (hashed, never retrievable
 * again), this one IS retrievable later by DispatchWebhookEventAction (it
 * must be, to sign every delivery) — only the *portal response* treats it
 * as one-time-reveal, the database itself keeps it (encrypted).
 */
final class CreateWebhookSubscriptionAction
{
    public function __construct(
        private readonly WebhookSubscriptionRepositoryInterface $subscriptions,
    ) {
    }

    /**
     * @param list<WebhookEvent> $events
     * @return array{subscription: WebhookSubscriptionData, secret: string}
     */
    public function execute(int $businessId, string $url, array $events): array
    {
        $secret = bin2hex(random_bytes(32));

        $subscription = WebhookSubscription::create($businessId, $url, $secret, $events);
        $saved = $this->subscriptions->save($subscription);

        return [
            'subscription' => WebhookSubscriptionData::fromEntity($saved),
            'secret' => $secret,
        ];
    }
}
