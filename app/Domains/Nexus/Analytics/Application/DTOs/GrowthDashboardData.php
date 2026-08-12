<?php

namespace App\Domains\Nexus\Analytics\Application\DTOs;

final class GrowthDashboardData
{
    /**
     * @param  list<array{cohortWeek: string, businessesRegistered: int, referredCount: int, invitesSent: int, invitesConverted: int}>  $cohorts
     * @param  list<array{variant: string, sent: int, converted: int, conversionRate: float}>  $variants
     */
    public function __construct(
        public readonly float $kFactor,
        public readonly int $invitesSent,
        public readonly int $invitesConverted,
        public readonly float $conversionRatePercent,
        public readonly int $invitingBusinesses,
        public readonly array $cohorts,
        public readonly array $variants,
    ) {
    }

    public function toArray(): array
    {
        return [
            'kFactor' => $this->kFactor,
            'invitesSent' => $this->invitesSent,
            'invitesConverted' => $this->invitesConverted,
            'conversionRatePercent' => $this->conversionRatePercent,
            'invitingBusinesses' => $this->invitingBusinesses,
            'cohorts' => $this->cohorts,
            'variants' => $this->variants,
        ];
    }
}
