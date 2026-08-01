<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Exceptions\ChannelSendFailedException;
use App\Modules\Notifications\Domain\Services\ChannelSenderInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * A real implementation: POSTs `{subject, body}` as JSON to `$recipient`
 * (the destination URL itself — a webhook has no separate "address" the
 * way email/SMS do). Guzzle client is injectable (same optional-constructor
 * shape WooCommerceClient already uses) so a test can supply a mock
 * handler instead of hitting a real network.
 */
final class WebhookSender implements ChannelSenderInterface
{
    private readonly ClientInterface $http;

    public function __construct(?ClientInterface $http = null)
    {
        $this->http = $http ?? new Client(['timeout' => 10]);
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        try {
            $this->http->request('POST', $recipient, [
                'json' => ['subject' => $subject, 'body' => $body],
            ]);
        } catch (GuzzleException $e) {
            throw new ChannelSendFailedException("Webhook POST to [{$recipient}] failed: {$e->getMessage()}", previous: $e);
        }
    }
}
