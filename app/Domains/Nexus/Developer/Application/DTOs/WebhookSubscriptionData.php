<?php

namespace App\Domains\Nexus\Developer\Application\DTOs;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookSubscription;

/**
 * Structured data transfer for a webhook subscription. Deliberately never
 * carries the secret (shown once, at creation time only, the same
 * one-time-reveal contract ApiKey's plaintext already follows) — represents
 * data only, no business logic (DTO Conventions).
 */
final class WebhookSubscriptionData
{
    /**
     * @param list<string> $events
     */
    public function __construct(
        public readonly int $id,
        public readonly string $url,
        public readonly array $events,
        public readonly bool $isRevoked,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(WebhookSubscription $subscription): self
    {
        return new self(
            id: $subscription->id(),
            url: $subscription->url(),
            events: array_map(fn ($event) => $event->value, $subscription->events()),
            isRevoked: $subscription->isRevoked(),
            createdAt: $subscription->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: int, url: string, events: list<string>, isRevoked: bool, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'events' => $this->events,
            'isRevoked' => $this->isRevoked,
            'createdAt' => $this->createdAt,
        ];
    }
}
