<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * Added unprompted (HANDOFF §3 pattern #12) — the request's own DB rules
 * named exactly these 3 values for `subscription_invoices.status` but
 * didn't list a dedicated enum for it the way `TrialPeriod`/`BillingCycle`
 * were explicitly named; every other status-shaped column in this codebase
 * (OrderStatus, PaymentStatus, TrackingStatus, ...) is a real enum, not a
 * bare string, so this follows the same convention rather than being the
 * one exception.
 */
enum SubscriptionInvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
}
