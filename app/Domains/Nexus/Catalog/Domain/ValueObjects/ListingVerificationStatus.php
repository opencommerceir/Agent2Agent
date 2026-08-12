<?php

namespace App\Domains\Nexus\Catalog\Domain\ValueObjects;

/**
 * A Catalog-local verification concept for a listing (Product/Service) —
 * deliberately its own enum, not a reuse of Business's own
 * VerificationStatus, per the codebase-wide "each domain builds its own
 * VOs" convention (Phase 1's Money precedent). `Rejected` exists here
 * (unlike Business's own two-state Pending/Verified) because a listing
 * can be reviewed and explicitly turned down without the whole Business
 * itself being unverified.
 */
enum ListingVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
