<?php

namespace App\Domains\Nexus\Approval\Domain\ValueObjects;

/**
 * `Pending -> Completed` (every level approved, in order) or `Pending ->
 * Rejected` (any level rejects) — both terminal. Named `Completed` rather
 * than `Approved` to avoid colliding with Negotiation's own `Accepted`
 * status: this only means "the chain finished," the actual Negotiation
 * acceptance is a separate, existing step (ApprovePendingNegotiationAction)
 * that ApproveApprovalLevelAction calls once this reaches Completed.
 */
enum ApprovalRequestStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
}
