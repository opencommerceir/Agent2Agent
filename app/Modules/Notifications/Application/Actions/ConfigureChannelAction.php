<?php

namespace App\Modules\Notifications\Application\Actions;

use App\Modules\Notifications\Application\DTOs\NotificationChannelData;
use App\Modules\Notifications\Domain\Entities\NotificationChannel;
use App\Modules\Notifications\Domain\Repositories\NotificationChannelRepositoryInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;

/**
 * Upserts by (tenantId, channelType) — see
 * NotificationChannelRepositoryInterface::save()'s own docblock.
 */
final class ConfigureChannelAction
{
    public function __construct(
        private readonly NotificationChannelRepositoryInterface $channels,
    ) {
    }

    public function execute(int $tenantId, string $channelType, array $config, bool $isActive = true): NotificationChannelData
    {
        $type = ChannelType::from($channelType);
        $channel = $this->channels->findByType($tenantId, $type);

        if ($channel) {
            $channel->configure($config, $isActive);
        } else {
            $channel = NotificationChannel::create($tenantId, $type, $config, $isActive);
        }

        $channel = $this->channels->save($channel);

        return NotificationChannelData::fromEntity($channel);
    }
}
