<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * `Expired` is modeled (rule §د's own 6-case list) but unreached by any
 * Action in this stage — the same "modeled but not all reachable" gap
 * `TransferStatus::InTransit` (Phase 5, Stage 2, §7.22) and
 * `RewardType::FreeProduct`/`FreeShipping` (§7.10) already carry. A
 * PastDue subscription that never recovers stays PastDue indefinitely
 * this stage; a future "close out abandoned past_due subscriptions after
 * N days" job is the natural place Expired would first become reachable
 * (see Subscription's own ALLOWED_TRANSITIONS docblock).
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Paused = 'paused';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
