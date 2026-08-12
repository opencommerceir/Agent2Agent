<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Growth\Domain\Entities\ReferralSignup;
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
 */
final class RecordReferralSignupAction
{
    public function __construct(
        private readonly ReferralCodeRepositoryInterface $codes,
        private readonly ReferralSignupRepositoryInterface $signups,
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

        return $this->signups->save(ReferralSignup::record($code->businessId(), $refereeBusinessId, $code->code()));
    }
}
