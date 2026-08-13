<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

/**
 * Phase 7/M10 — a Business's *declared* data residency preference, for
 * compliance reporting only. This platform runs on a single database in
 * a single region today (no per-region infrastructure, no data
 * partitioning) — actually enforcing "this Business's data physically
 * lives in region X" is Phase 10's "Regional Compliance"/"Data residency
 * منطقه‌ای" scope (docs/nexus-roadmap.md), not this one. Recording the
 * preference now is still real and useful: it's what a compliance
 * questionnaire or SOC 2 auditor asks for first, and the Compliance
 * Dashboard (GetComplianceOverviewAction) reports the declared breakdown
 * honestly as "declared", never as "enforced". Same documented-shortcut
 * tier as Escrow (Phase 3/M4) and the SAML/LDAP stubs (Phase 7/M8).
 */
enum DataResidencyRegion: string
{
    case Iran = 'ir';
    case EU = 'eu';
    case US = 'us';
    case GCC = 'gcc';
    case Other = 'other';
}
