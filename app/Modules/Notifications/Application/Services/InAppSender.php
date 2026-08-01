<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Services\ChannelSenderInterface;

/**
 * Trivial by design: the persisted Notification row *is* the in-app
 * notification (a UI polls `notification.message.list` filtered to
 * `channel: in_app`) — there is nothing external to deliver to, so this
 * "send" never fails.
 */
final class InAppSender implements ChannelSenderInterface
{
    public function send(string $recipient, string $subject, string $body): void
    {
        // Intentionally a no-op — see this class's own docblock.
    }
}
