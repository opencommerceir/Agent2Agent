<?php

namespace App\Modules\Notifications\Domain\ValueObjects;

/**
 * Added for `NotificationPreference` (correction #3 in this stage's own
 * plan) — the same type-safety `App\Core\Domain\ValueObjects\MemberType`
 * already gives Core, so "whose preference is this" is never a bare
 * string.
 */
enum RecipientType: string
{
    case Customer = 'customer';
    case Agent = 'agent';
}
