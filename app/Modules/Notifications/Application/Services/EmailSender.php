<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Domain\Exceptions\ChannelSendFailedException;
use App\Modules\Notifications\Domain\Services\ChannelSenderInterface;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The one real ChannelSenderInterface implementation this stage — unlike
 * every Connector before this module, no Mock class is needed: Laravel's
 * own `Mail` facade already ships a testing-safe fake
 * (`MAIL_MAILER=array` in phpunit.xml captures every send in-memory,
 * nothing touches a real network in tests). Uses `Mail::raw()` — a plain
 * text body, since `body`/`subject` are already-rendered strings from
 * TemplateRenderer, not a Blade view.
 */
final class EmailSender implements ChannelSenderInterface
{
    public function send(string $recipient, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($recipient, $subject): void {
                $message->to($recipient)->subject($subject);
            });
        } catch (Throwable $e) {
            throw new ChannelSendFailedException("Email send failed: {$e->getMessage()}", previous: $e);
        }
    }
}
