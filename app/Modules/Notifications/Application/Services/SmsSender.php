<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Exceptions\ChannelSendFailedException;
use App\Modules\Notifications\Domain\Services\ChannelSenderInterface;

/**
 * **Explicitly a stub, not a real gateway** — no SMS provider (Twilio or
 * otherwise) credentials or API shape were given, unlike Email (Laravel's
 * own mailer) or Webhook (a plain HTTP POST). Always succeeds unless
 * `simulateFailure()` is set, the same `MockPaymentGateway`/
 * `MockWooCommerceHttpClient` "deliberate, documented test-triggering
 * convention" every prior Mock in this codebase already establishes. A
 * real implementation is real future work, not silently broken behavior
 * — nothing about this class pretends an SMS was actually delivered
 * anywhere.
 */
final class SmsSender implements ChannelSenderInterface
{
    public function __construct(
        private bool $simulateFailure = false,
    ) {
    }

    public function simulateFailure(bool $shouldFail = true): void
    {
        $this->simulateFailure = $shouldFail;
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        if ($this->simulateFailure) {
            throw new ChannelSendFailedException("Simulated SMS delivery failure to [{$recipient}].");
        }
    }
}
