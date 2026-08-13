<?php

namespace App\Domains\Nexus\Analytics\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Business\Infrastructure\Models\BusinessOwner;
use App\Domains\Nexus\Contract\Infrastructure\Models\DisputeCase;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Business/BusinessOwner/DisputeCase for the Compliance Dashboard
 * (Phase 7/M10). Same "Infrastructure\Queries\*, not a bloated Repository"
 * convention RevenueQuery/GrowthAnalyticsQuery already established.
 */
class ComplianceQuery
{
    public function totalBusinesses(): int
    {
        return Business::query()->count();
    }

    public function suspendedBusinessCount(): int
    {
        return Business::query()->where('status', 'suspended')->count();
    }

    /**
     * @return array<string, int> region value (or "undeclared") => count
     */
    public function dataResidencyBreakdown(): array
    {
        $breakdown = Business::query()
            ->whereNotNull('data_residency_region')
            ->groupBy('data_residency_region')
            ->selectRaw('data_residency_region, count(*) as aggregate')
            ->pluck('aggregate', 'data_residency_region')
            ->map(fn ($count) => (int) $count)
            ->all();

        $breakdown['undeclared'] = Business::query()->whereNull('data_residency_region')->count();

        return $breakdown;
    }

    public function ownersWithMfaEnabledCount(): int
    {
        return BusinessOwner::query()->whereNotNull('mfa_enabled_at')->count();
    }

    public function totalOwnersCount(): int
    {
        return BusinessOwner::query()->count();
    }

    public function openDisputeCount(): int
    {
        return DisputeCase::query()->whereIn('status', ['open', 'mediation'])->count();
    }
}
