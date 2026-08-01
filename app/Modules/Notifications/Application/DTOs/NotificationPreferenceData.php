<?php

namespace App\Modules\Notifications\Application\DTOs;

use App\Modules\Notifications\Domain\Entities\NotificationPreference;

/**
 * Not in this stage's original DTO list — added alongside
 * NotificationPreferenceRepositoryInterface (that interface's own
 * docblock has the reasoning): every other capability in this codebase
 * returns a *Data DTO, never a raw array.
 */
final class NotificationPreferenceData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $recipientType,
        public readonly int $recipientId,
        public readonly string $notificationType,
        public readonly string $channelType,
        public readonly bool $isEnabled,
    ) {
    }

    public static function fromEntity(NotificationPreference $preference): self
    {
        return new self(
            id: $preference->id(),
            tenantId: $preference->tenantId(),
            recipientType: $preference->recipientType()->value,
            recipientId: $preference->recipientId(),
            notificationType: $preference->notificationType()->value,
            channelType: $preference->channelType()->value,
            isEnabled: $preference->isEnabled(),
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
            'recipientType' => $this->recipientType,
            'recipientId' => $this->recipientId,
            'notificationType' => $this->notificationType,
            'channelType' => $this->channelType,
            'isEnabled' => $this->isEnabled,
        ];
    }
}
