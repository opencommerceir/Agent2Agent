<?php

namespace App\Modules\Notifications\Domain\Entities;

use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\DeliveryStatus;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\Recipient;
use DateTimeImmutable;

/**
 * One sent (or attempted) Notification. `markSent()`/`markFailed()` are
 * the only two reachable transitions this stage — `DeliveryStatus::Delivered`
 * is modeled but nothing calls a `markDelivered()` yet (see that enum's
 * own docblock for why).
 */
final class Notification
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly NotificationType $type,
        private readonly ChannelType $channelType,
        private readonly Recipient $recipient,
        private readonly string $subject,
        private readonly string $body,
        private readonly ?int $templateId,
        private DeliveryStatus $status,
        private ?DateTimeImmutable $sentAt,
        private ?DateTimeImmutable $deliveredAt,
        private ?DateTimeImmutable $failedAt,
        private ?string $errorMessage,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        NotificationType $type,
        ChannelType $channelType,
        Recipient $recipient,
        string $subject,
        string $body,
        ?int $templateId = null,
        array $metadata = [],
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            type: $type,
            channelType: $channelType,
            recipient: $recipient,
            subject: $subject,
            body: $body,
            templateId: $templateId,
            status: DeliveryStatus::Pending,
            sentAt: null,
            deliveredAt: null,
            failedAt: null,
            errorMessage: null,
            metadata: $metadata,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function markSent(): void
    {
        $this->status = DeliveryStatus::Sent;
        $this->sentAt = new DateTimeImmutable();
        $this->errorMessage = null;
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = DeliveryStatus::Failed;
        $this->failedAt = new DateTimeImmutable();
        $this->errorMessage = $errorMessage;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function type(): NotificationType
    {
        return $this->type;
    }

    public function channelType(): ChannelType
    {
        return $this->channelType;
    }

    public function recipient(): Recipient
    {
        return $this->recipient;
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function templateId(): ?int
    {
        return $this->templateId;
    }

    public function status(): DeliveryStatus
    {
        return $this->status;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function deliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function failedAt(): ?DateTimeImmutable
    {
        return $this->failedAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
