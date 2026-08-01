<?php

namespace App\Modules\Notifications\Domain\Entities;

use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use App\Modules\Notifications\Domain\ValueObjects\NotificationType;
use App\Modules\Notifications\Domain\ValueObjects\RecipientType;
use DateTimeImmutable;

/**
 * One opt-in/opt-out row for `(tenant, recipientType, recipientId,
 * notificationType, channelType)` — `NotificationDispatcher`'s own
 * docblock explains the opt-*out* default this implies: no row at all
 * means "send," a row with `isEnabled() === false` means "don't."
 */
final class NotificationPreference
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $tenantId,
        private readonly RecipientType $recipientType,
        private readonly int $recipientId,
        private readonly NotificationType $notificationType,
        private readonly ChannelType $channelType,
        private bool $isEnabled,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        int $tenantId,
        RecipientType $recipientType,
        int $recipientId,
        NotificationType $notificationType,
        ChannelType $channelType,
        bool $isEnabled,
    ): self {
        return new self(
            id: null,
            tenantId: $tenantId,
            recipientType: $recipientType,
            recipientId: $recipientId,
            notificationType: $notificationType,
            channelType: $channelType,
            isEnabled: $isEnabled,
            createdAt: new DateTimeImmutable(),
        );
    }

    public function setEnabled(bool $isEnabled): void
    {
        $this->isEnabled = $isEnabled;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tenantId(): int
    {
        return $this->tenantId;
    }

    public function recipientType(): RecipientType
    {
        return $this->recipientType;
    }

    public function recipientId(): int
    {
        return $this->recipientId;
    }

    public function notificationType(): NotificationType
    {
        return $this->notificationType;
    }

    public function channelType(): ChannelType
    {
        return $this->channelType;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
