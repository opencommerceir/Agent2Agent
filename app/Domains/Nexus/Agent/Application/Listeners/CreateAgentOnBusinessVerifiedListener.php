<?php

namespace App\Domains\Nexus\Agent\Application\Listeners;

use App\Domains\Nexus\Agent\Application\Actions\CreateAgentForBusinessAction;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;

/**
 * "ساخت خودکار Agent پس از تأیید نهایی" (docs/nexus-roadmap.md, Phase 1) —
 * reacts to Business's own BusinessWasVerified event rather than
 * VerifyBusinessAction calling into the Agent domain directly (Inter-Module
 * Communication, docs/modules.md). The event already carries the full
 * Business entity, so this listener needs no dependency on the Business
 * domain's repository.
 */
final class CreateAgentOnBusinessVerifiedListener
{
    public function __construct(
        private readonly CreateAgentForBusinessAction $createAgent,
    ) {
    }

    public function handle(BusinessWasVerified $event): void
    {
        $business = $event->business;

        $this->createAgent->execute(
            businessId: $business->id(),
            tenantId: $business->tenantId(),
            organizationId: $business->organizationId(),
            nameFa: $business->nameFa(),
            nameEn: $business->nameEn(),
        );
    }
}
