<?php

namespace App\Domains\Nexus\Developer\Domain\ValueObjects;

/**
 * The four categories docs/nexus-roadmap.md's Integration Marketplace
 * bullet names verbatim (ERP, CRM, Accounting, Logistics) — a label on an
 * otherwise identical generic connector (IntegrationConnection), not four
 * different behaviors. No real vendor SDK (SAP/QuickBooks/HubSpot/etc.)
 * is wired to any of them — see IntegrationConnection's own docblock.
 */
enum IntegrationCategory: string
{
    case Erp = 'erp';
    case Crm = 'crm';
    case Accounting = 'accounting';
    case Logistics = 'logistics';
}
