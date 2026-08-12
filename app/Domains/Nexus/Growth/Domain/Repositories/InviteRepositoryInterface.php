<?php

namespace App\Domains\Nexus\Growth\Domain\Repositories;

use App\Domains\Nexus\Growth\Domain\Entities\Invite;

interface InviteRepositoryInterface
{
    public function findById(int $id): ?Invite;

    /**
     * Oldest-first unconverted match for a given referral code — a
     * referrer's single code can be handed out to many invitees, so
     * RecordReferralSignupAction (which only knows the code, not which
     * invitee actually registered) resolves conversion to whichever Invite
     * on that code is still open, first-sent-first-matched.
     */
    public function findOldestUnconvertedByReferralCode(string $referralCode): ?Invite;

    /**
     * @return list<Invite>
     */
    public function findByInviterId(int $inviterBusinessId): array;

    public function save(Invite $invite): Invite;
}
