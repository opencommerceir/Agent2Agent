<?php

namespace App\Domains\Nexus\Developer\Domain\Entities;

use App\Domains\Nexus\Developer\Domain\ValueObjects\WebhookEvent;
use DateTimeImmutable;

/**
 * One immutable row per attempted HTTP delivery — the same append-only
 * ledger shape CreditTransaction (Phase 3/M1) and LLMUsageLog (Phase 4/M3)
 * already established, here for webhook delivery attempts instead of
 * credit/LLM spend. Never mutated after `record()`; a retry (if ever
 * added) would be a new row, not an update to this one.
 */
final class WebhookDeliveryLog
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $businessId,
        private readonly int $subscriptionId,
        private readonly WebhookEvent $event,
        private readonly string $url,
        private readonly bool $succeeded,
        private readonly ?int $httpStatus,
        private readonly ?string $errorMessage,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function record(
        int $businessId,
        int $subscriptionId,
        WebhookEvent $event,
        string $url,
        bool $succeeded,
        ?int $httpStatus,
        ?string $errorMessage,
    ): self {
        return new self(
            id: null,
            businessId: $businessId,
            subscriptionId: $subscriptionId,
            event: $event,
            url: $url,
            succeeded: $succeeded,
            httpStatus: $httpStatus,
            errorMessage: $errorMessage,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function businessId(): int
    {
        return $this->businessId;
    }

    public function subscriptionId(): int
    {
        return $this->subscriptionId;
    }

    public function event(): WebhookEvent
    {
        return $this->event;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function succeeded(): bool
    {
        return $this->succeeded;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
