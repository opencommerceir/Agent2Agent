<?php

namespace App\Domains\Nexus\Credit\Domain\ValueObjects;

/**
 * Ledger entry kind — same plain string-backed enum shape as
 * NegotiationMessageType, framework-free.
 */
enum CreditTransactionType: string
{
    case Purchase = 'purchase';
    case Deduction = 'deduction';
    case Refund = 'refund';
    case AdminGrant = 'admin_grant';
    case ReferralBonus = 'referral_bonus';

    // Phase 7/M2 — a member Business funding, or the CostGate spending
    // from, its Holding's shared pool. Additive cases, same pattern
    // ReferralBonus already established.
    case PoolContribution = 'pool_contribution';
    case PoolDeduction = 'pool_deduction';
}
