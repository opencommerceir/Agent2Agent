<?php

namespace App\Domains\Nexus\Growth\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Growth\Application\DTOs\ReferralStatusData;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralCodeRepositoryInterface;
use App\Domains\Nexus\Growth\Domain\Repositories\ReferralSignupRepositoryInterface;

/**
 * Read model for the Referral dashboard/MCP capability. Tier-2 is counted,
 * not listed by name — it's the roadmap's "Multi-tier tracking" signal
 * (how far this Business's own network reaches), not something a Business
 * gets a reward breakdown for individually (only tier-1 referrer/referee
 * and the tier-2 referrer get an actual credit grant —
 * GrantReferralRewardOnBusinessVerifiedListener).
 */
final class GetReferralStatusAction
{
    public function __construct(
        private readonly ReferralCodeRepositoryInterface $codes,
        private readonly ReferralSignupRepositoryInterface $signups,
        private readonly BusinessRepositoryInterface $businesses,
    ) {
    }

    public function execute(int $businessId): ReferralStatusData
    {
        $code = $this->codes->findByBusinessId($businessId);
        $tier1 = $this->signups->findByReferrerId($businessId);

        $tier2Count = 0;
        $referrals = [];

        foreach ($tier1 as $signup) {
            $tier2Count += $this->signups->countByReferrerId($signup->refereeBusinessId());

            $referee = $this->businesses->findById($signup->refereeBusinessId());

            $referrals[] = [
                'nameFa' => $referee?->nameFa() ?? '',
                'nameEn' => $referee?->nameEn() ?? '',
                'status' => $signup->status()->value,
                'createdAt' => $signup->createdAt()->format(DATE_ATOM),
            ];
        }

        return new ReferralStatusData(
            code: $code?->code(),
            tier1Count: count($tier1),
            tier1RewardedCount: count(array_filter($tier1, fn ($s) => ! $s->isPending())),
            tier2Count: $tier2Count,
            referrals: $referrals,
        );
    }
}
