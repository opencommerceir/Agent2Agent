<?php

namespace App\Modules\Notifications\Application\DTOs;

use App\Modules\Notifications\Domain\Entities\Notification;

final class NotificationData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $type,
        public readonly string $channelType,
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $body,
        public readonly ?int $templateId,
        public readonly string $status,
        public readonly ?string $sentAt,
        public readonly ?string $deliveredAt,
        public readonly ?string $failedAt,
        public readonly ?string $errorMessage,
        public readonly array $metadata,
    ) {
    }

    public static function fromEntity(Notification $notification): self
    {
        return new self(
            id: $notification->id(),
            tenantId: $notification->tenantId(),
            type: $notification->type()->value,
            channelType: $notification->channelType()->value,
            recipient: $notification->recipient()->value(),
            subject: $notification->subject(),
            body: $notification->body(),
            templateId: $notification->templateId(),
            status: $notification->status()->value,
            sentAt: $notification->sentAt()?->format(DATE_ATOM),
            deliveredAt: $notification->deliveredAt()?->format(DATE_ATOM),
            failedAt: $notification->failedAt()?->format(DATE_ATOM),
            errorMessage: $notification->errorMessage(),
            metadata: $notification->metadata(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenantId' => $this->tenantId,
            'type' => $this->type,
            'channelType' => $this->channelType,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'templateId' => $this->templateId,
            'status' => $this->status,
            'sentAt' => $this->sentAt,
            'deliveredAt' => $this->deliveredAt,
            'failedAt' => $this->failedAt,
            'errorMessage' => $this->errorMessage,
            'metadata' => $this->metadata,
        ];
    }
}
