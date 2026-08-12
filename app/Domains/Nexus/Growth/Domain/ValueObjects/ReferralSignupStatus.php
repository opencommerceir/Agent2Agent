<?php

namespace App\Domains\Nexus\Growth\Domain\ValueObjects;

/**
 * A signup only pays out once the referee is actually Verified (Business's
 * own event) — same "no reward for an unverified/fake account" honesty this
 * codebase already applies to CreditBalance provisioning. Pending rows that
 * never verify simply never transition; nothing prunes them (same
 * documented-gap shape as Negotiation::Expired).
 */
enum ReferralSignupStatus: string
{
    case Pending = 'pending';
    case Rewarded = 'rewarded';
}
