<?php

namespace App\Modules\Finance\Domain\ValueObjects;

/**
 * Invoice::issue() is currently the only transition this codebase drives
 * (Draft -> Issued) — Paid/Cancelled are real, modeled states with no
 * Action requested yet to reach them this stage, the same
 * "described, not yet all reachable" gap Ticket's Closed state briefly
 * had in CRM Foundation before UpdateTicketAction existed.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
