<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Services\ChannelSenderInterface;
use App\Modules\Notifications\Domain\ValueObjects\ChannelType;
use InvalidArgumentException;

/**
 * In-memory lookup of "which Sender handles which ChannelType" — the
 * third time this codebase builds this exact shape (Commerce's
 * ConnectorRegistry, Shipping's ShippingProviderRegistry), now a fully
 * established convention. Registered once in
 * NotificationsServiceProvider::boot() with all 4 Senders.
 */
final class ChannelSenderRegistry
{
    /**
     * @var array<string, ChannelSenderInterface>
     */
    private array $senders = [];

    public function register(ChannelType $channelType, ChannelSenderInterface $sender): void
    {
        $this->senders[$channelType->value] = $sender;
    }

    public function get(ChannelType $channelType): ChannelSenderInterface
    {
        if (! isset($this->senders[$channelType->value])) {
            throw new InvalidArgumentException("No sender registered for channel [{$channelType->value}].");
        }

        return $this->senders[$channelType->value];
    }
}
