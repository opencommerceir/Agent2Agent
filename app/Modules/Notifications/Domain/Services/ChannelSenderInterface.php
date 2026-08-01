<?php

namespace App\Modules\Notifications\Domain\Services;

use App\Modules\Notifications\Domain\Exceptions\ChannelSendFailedException;

/**
 * One implementation per ChannelType (Email/Sms/Webhook/InApp),
 * registered into Application\Services\ChannelSenderRegistry — the third
 * time this codebase builds the exact ConnectorRegistry/
 * ShippingProviderRegistry in-memory-lookup-by-key shape, now a fully
 * established convention. A Sender's only job is delivering an
 * already-rendered subject/body to an already-resolved recipient string
 * — no template rendering, no preference checking, no retry logic (all
 * three live in SendNotificationAction/TemplateRenderer/NotificationDispatcher
 * instead).
 */
interface ChannelSenderInterface
{
    /**
     * @throws ChannelSendFailedException
     */
    public function send(string $recipient, string $subject, string $body): void;
}
