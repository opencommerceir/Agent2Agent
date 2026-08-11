<?php

namespace App\Domains\Nexus\Business\Domain\Events;

use App\Domains\Nexus\Business\Domain\Entities\Business;

/**
 * Domain event: a fact that already happened (Event Conventions).
 * Dispatched after a Business's verification_status transitions to
 * Verified. M3's Agent domain listens for this to auto-create the
 * Business's Agent ("ساخت خودکار Agent پس از تأیید نهایی",
 * docs/nexus-roadmap.md) — Event Driven Architecture, not a direct call
 * from Business into Agent.
 */
final class BusinessWasVerified
{
    public function __construct(
        public readonly Business $business,
    ) {
    }
}
