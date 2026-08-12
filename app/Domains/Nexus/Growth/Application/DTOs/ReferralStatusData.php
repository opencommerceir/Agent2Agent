<?php

namespace App\Domains\Nexus\Growth\Application\DTOs;

/**
 * Read-model for the Referral dashboard (business-facing) and the
 * `nexus.growth.referral.status` MCP capability — same "one DTO, two
 * consumers" shape BusinessData/NegotiationData already use.
 */
final class ReferralStatusData
{
    /**
     * @param  list<array{nameFa: string, nameEn: string, status: string, createdAt: string}>  $referrals
     */
    public function __construct(
        public readonly ?string $code,
        public readonly int $tier1Count,
        public readonly int $tier1RewardedCount,
        public readonly int $tier2Count,
        public readonly array $referrals,
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'tier1Count' => $this->tier1Count,
            'tier1RewardedCount' => $this->tier1RewardedCount,
            'tier2Count' => $this->tier2Count,
            'referrals' => $this->referrals,
        ];
    }
}
