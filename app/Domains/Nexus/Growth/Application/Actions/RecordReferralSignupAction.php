<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralSignup;
use App\Domains\Nexus\Growth\Domain\Repositories\InviteRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;

/**
 * Called once, right after RegisterBusinessController creates the new
 * Business — deliberately NOT folded into RegisterBusinessAction itself
 * (leaves that Action's signature/tests untouched, same reasoning
 * BusinessOwner creation already stays in the Controller rather than the
 * Action). An unknown/malformed code is a silent no-op, never a
 * registration-blocking error — a mistyped `?ref=` in a shared link must
 * never stop someone from signing up.
 *
 * Also resolves Invite conversion (Phase 5/M2): the same code can be
 * shared generically (dashboard "copy link") or handed to one named lead
 * via SendAgentInviteAction — both paths funnel through this one Action, so
 * "oldest still-open Invite on this code" is the best honest match (the
 * registrant's own account email frequently differs from the person the
 * Agent originally emailed).
 */
final class RecordReferralSignupAction
{
    public function __construct(
        private readonly ReferralCodeRepositoryInterface $codes,
        private readonly ReferralSignupRepositoryInterface $signups,
        private readonly InviteRepositoryInterface $invites,
    ) {
    }

    public function execute(int $refereeBusinessId, ?string $referralCode): ?ReferralSignup
    {
        if (! $referralCode) {
            return null;
        }

        $code = $this->codes->findByCode($referralCode);

        if (! $code || $code->businessId() === $refereeBusinessId) {
            return null;
        }

        if ($this->signups->findByRefereeId($refereeBusinessId)) {
            return null; // already recorded (e.g. duplicate form submit)
        }

        $signup = $this->signups->save(ReferralSignup::record($code->businessId(), $refereeBusinessId, $code->code()));

        $invite = $this->invites->findOldestUnconvertedByReferralCode($code->code());

        if ($invite) {
            $invite->convert($refereeBusinessId);
            $this->invites->save($invite);
        }

        return $signup;
    }
}
