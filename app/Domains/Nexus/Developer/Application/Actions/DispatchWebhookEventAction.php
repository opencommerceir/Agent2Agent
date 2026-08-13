<?php

namespace App\Domains\Nexus\Developer\Application\Actions;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookDeliveryLog;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookDeliveryLogRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\Repositories\WebhookSubscriptionRepositoryInterface;
use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Carbon;

/**
 * The actual HTTP delivery for a WebhookEvent, called by each of the
 * three per-event Listeners (DispatchWebhookOn*Listener). Deliberately
 * does NOT go through the Notifications module's SendNotificationAction/
 * WebhookSender (Phase 5's outbound-email precedent) the way most other
 * Nexus outbound sends do — that channel's send() signature
 * (`string $recipient, string $subject, string $body`) has no way to
 * attach a custom header, and an HMAC-signed webhook is useless without
 * one (the receiver can't verify authenticity from the body alone). This
 * is the same "genuinely different shape, so a new adapter, not a forced
 * reuse" call Phase 4/M2 made for LLMProviderInterface vs
 * AgentOrchestrator's own LLMClientInterface — the HTTP mechanics
 * themselves (injectable Guzzle client, catch GuzzleException, 10s
 * timeout) still follow the exact style every other Guzzle-based
 * connector in this codebase already uses (WebhookSender included).
 *
 * Every attempt — success or failure — produces exactly one
 * WebhookDeliveryLog row; a delivery failure never throws back to the
 * domain event listener that triggered it (a webhook receiver being down
 * must never break the Negotiation/Escrow/Contract flow that fired the
 * event).
 */
final class DispatchWebhookEventAction
{
    private readonly ClientInterface $http;

    public function __construct(
        private readonly WebhookSubscriptionRepositoryInterface $subscriptions,
        private readonly WebhookDeliveryLogRepositoryInterface $deliveries,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client(['timeout' => 10]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function execute(int $businessId, WebhookEvent $event, array $payload): void
    {
        $subscriptions = $this->subscriptions->findActiveByBusinessIdAndEvent($businessId, $event);

        foreach ($subscriptions as $subscription) {
            $body = json_encode([
                'event' => $event->value,
                'data' => $payload,
                'timestamp' => Carbon::now()->toAtomString(),
            ], JSON_THROW_ON_ERROR);

            $signature = hash_hmac('sha256', $body, $subscription->secret());

            [$succeeded, $httpStatus, $errorMessage] = $this->send($subscription->url(), $body, $event, $signature);

            $this->deliveries->save(WebhookDeliveryLog::record(
                businessId: $businessId,
                subscriptionId: $subscription->id(),
                event: $event,
                url: $subscription->url(),
                succeeded: $succeeded,
                httpStatus: $httpStatus,
                errorMessage: $errorMessage,
            ));
        }
    }

    /**
     * @return array{0: bool, 1: ?int, 2: ?string}
     */
    private function send(string $url, string $body, WebhookEvent $event, string $signature): array
    {
        try {
            $response = $this->http->request('POST', $url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Nexus-Event' => $event->value,
                    'X-Nexus-Signature' => "sha256={$signature}",
                ],
            ]);

            return [true, $response->getStatusCode(), null];
        } catch (GuzzleException $e) {
            return [false, null, $e->getMessage()];
        }
    }
}
