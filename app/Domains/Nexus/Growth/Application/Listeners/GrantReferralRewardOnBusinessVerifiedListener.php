<?php

namespace App\Domains\Nexus\Growth\Application\Listeners;

use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Credit\Application\Actions\GrantCreditsAction;
use App\Domains\Nexus\Credit\Domain\ValueObjects\CreditTransactionType;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;

/**
 * The reward only ever pays out here — never at signup time
 * (RecordReferralSignupAction only records a Pending row) — a referral for
 * a Business that never gets past admin verification is worthless, never
 * rewarded, same "no gaming an unverified account" honesty
 * GrantStartingCreditsOnBusinessVerifiedListener already relies on for the
 * starting balance itself.
 *
 * Two-sided (roadmap: "پاداش کردیت دوطرفه"): the referrer AND the newly
 * verified referee both get credited. Multi-tier (roadmap: "Multi-tier
 * tracking"): if the referrer was themselves a rewarded referee of someone
 * else, that tier-2 referrer gets a smaller bonus too — one extra hop only,
 * not unbounded recursion, matching the roadmap's "Multi-tier" (plural,
 * bounded) rather than an infinite chain.
 */
final class GrantReferralRewardOnBusinessVerifiedListener
{
    public function __construct(
        private readonly ReferralSignupRepositoryInterface $signups,
        private readonly GrantCreditsAction $grantCredits,
    ) {
    }

    public function handle(BusinessWasVerified $event): void
    {
        $refereeId = $event->business->id();
        $signup = $this->signups->findByRefereeId($refereeId);

        if (! $signup || ! $signup->isPending()) {
            return;
        }

        $referrerReward = (int) config('nexus.platform.growth.referral.referrer_reward_credits', 0);
        $refereeReward = (int) config('nexus.platform.growth.referral.referee_reward_credits', 0);

        if ($referrerReward > 0) {
            $this->grantCredits->execute(
                businessId: $signup->referrerBusinessId(),
                amount: $referrerReward,
                type: CreditTransactionType::ReferralBonus,
                reason: 'growth.referral.reward.referrer',
                relatedId: $signup->id(),
            );
        }

        if ($refereeReward > 0) {
            $this->grantCredits->execute(
                businessId: $refereeId,
                amount: $refereeReward,
                type: CreditTransactionType::ReferralBonus,
                reason: 'growth.referral.reward.referee',
                relatedId: $signup->id(),
            );
        }

        $signup->reward();
        $this->signups->save($signup);

        $this->grantTier2Reward($signup->referrerBusinessId());
    }

    private function grantTier2Reward(int $referrerBusinessId): void
    {
        $tier2Reward = (int) config('nexus.platform.growth.referral.tier2_reward_credits', 0);

        if ($tier2Reward <= 0) {
            return;
        }

        $referrerOwnSignup = $this->signups->findByRefereeId($referrerBusinessId);

        if (! $referrerOwnSignup || $referrerOwnSignup->isPending()) {
            return; // the referrer wasn't itself a (rewarded) referral — no tier-2 chain
        }

        $this->grantCredits->execute(
            businessId: $referrerOwnSignup->referrerBusinessId(),
            amount: $tier2Reward,
            type: CreditTransactionType::ReferralBonus,
            reason: 'growth.referral.reward.tier2',
            relatedId: $referrerOwnSignup->id(),
        );
    }
}
