<?php

namespace App\Domains\Nexus\Business\Domain\ValueObjects;

/**
 * A raw string column would let an invalid value slip in silently — same
 * reasoning Core's TenantStatus/OrganizationMemberRole already follow.
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
}
