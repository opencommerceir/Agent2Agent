<?php

namespace App\Domains\Nexus\Growth\Application\Listeners;

use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Growth\Application\Actions\IssueReferralCodeAction;

/**
 * Every verified Business gets a shareable code from day one — same
 * event-driven auto-provisioning shape CreateAgentOnBusinessVerifiedListener
 * and GrantStartingCreditsOnBusinessVerifiedListener already established on
 * this exact event (Inter-Module Communication, docs/modules.md: react to
 * Business's own event, never a direct call into the Growth domain).
 */
final class IssueReferralCodeOnBusinessVerifiedListener
{
    public function __construct(
        private readonly IssueReferralCodeAction $issueReferralCode,
    ) {
    }

    public function handle(BusinessWasVerified $event): void
    {
        $this->issueReferralCode->execute($event->business->id());
    }
}
