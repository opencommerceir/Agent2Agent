<?php

namespace App\Domains\Nexus\Developer\Domain\Entities;

use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use DateTimeImmutable;

/**
 * A Business's subscription to be POSTed a payload whenever one of its
 * chosen WebhookEvents happens (Phase 9/M3). Revocable the same
 * nullable-`revokedAt` shape ApiKey (Phase 9/M1) already established —
 * deliberately not a hard delete, so DispatchWebhookEventAction's history
 * and the WebhookDeliveryLog it produces both stay attributable after
 * revocation.
 */
final class WebhookSubscription
{
    /**
     * @param list<WebhookEvent> $events
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly string $url,
        private readonly string $secret,
        private readonly array $events,
        private ?DateTimeImmutable $revokedAt,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<WebhookEvent> $events
     */
    public static function create(int $businessId, string $url, string $secret, array $events): self
    {
        return new self(
            id: null,
            businessId: $businessId,
            url: $url,
            secret: $secret,
            events: $events,
            revokedAt: null,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function isSubscribedTo(WebhookEvent $event): bool
    {
        return ! $this->isRevoked() && in_array($event, $this->events, true);
    }

    public function revoke(): void
    {
        $this->revokedAt = new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function secret(): string
    {
        return $this->secret;
    }

    /**
     * @return list<WebhookEvent>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
