<?php

namespace App\Modules\Notifications\Domain\Services;

use App\Modules\Notifications\Domain\Entities\NotificationPreference;

/**
 * Pure decision logic — never touches a Repository itself (Domain must
 * not depend on Infrastructure). Takes whatever SendNotificationAction
 * (Application layer) already fetched, the same "only combines what it's
 * given" shape Commerce's PricingService/Workflows' WorkflowEvaluator
 * already establish.
 *
 * Opt-*out* model: no Preference row at all means "send" — a Customer
 * only ever suppresses a notification type by explicitly disabling it
 * (`notification.preference.set`), matching this stage's own end-to-end
 * test (sending happens by default until disabled).
 */
final class NotificationDispatcher
{
    public function shouldSend(?NotificationPreference $preference, bool $channelActive): bool
    {
        if (! $channelActive) {
            return false;
        }

        if ($preference !== null && ! $preference->isEnabled()) {
            return false;
        }

        return true;
    }
}
