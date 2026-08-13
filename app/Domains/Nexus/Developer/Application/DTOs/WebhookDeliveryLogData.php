<?php

namespace App\Domains\Nexus\Developer\Application\DTOs;

use App\Domains\Nexus\Developer\Domain\Entities\WebhookDeliveryLog;

/**
 * Structured data transfer for a webhook delivery-attempt log row.
 * Represents data only — no business logic (DTO Conventions).
 */
final class WebhookDeliveryLogData
{
    public function __construct(
        public readonly int $id,
        public readonly int $subscriptionId,
        public readonly string $event,
        public readonly string $url,
        public readonly bool $succeeded,
        public readonly ?int $httpStatus,
        public readonly ?string $errorMessage,
        public readonly string $createdAt,
    ) {
    }

    public static function fromEntity(WebhookDeliveryLog $log): self
    {
        return new self(
            id: $log->id(),
            subscriptionId: $log->subscriptionId(),
            event: $log->event()->value,
            url: $log->url(),
            succeeded: $log->succeeded(),
            httpStatus: $log->httpStatus(),
            errorMessage: $log->errorMessage(),
            createdAt: $log->createdAt()->format(DATE_ATOM),
        );
    }

    /**
     * @return array{id: int, subscriptionId: int, event: string, url: string, succeeded: bool, httpStatus: ?int, errorMessage: ?string, createdAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subscriptionId' => $this->subscriptionId,
            'event' => $this->event,
            'url' => $this->url,
            'succeeded' => $this->succeeded,
            'httpStatus' => $this->httpStatus,
            'errorMessage' => $this->errorMessage,
            'createdAt' => $this->createdAt,
        ];
    }
}
