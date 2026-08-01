<?php

namespace App\Modules\Notifications\Application\DTOs;

use App\Modules\Notifications\Domain\Entities\NotificationChannel;

final class NotificationChannelData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $tenantId,
        public readonly string $channelType,
        public readonly array $config,
        public readonly bool $isActive,
    ) {
    }

    public static function fromEntity(NotificationChannel $channel): self
    {
        return new self(
            id: $channel->id(),
            tenantId: $channel->tenantId(),
            channelType: $channel->channelType()->value,
            config: $channel->config(),
            isActive: $channel->isActive(),
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
            'channelType' => $this->channelType,
            'config' => $this->config,
            'isActive' => $this->isActive,
        ];
    }
}
