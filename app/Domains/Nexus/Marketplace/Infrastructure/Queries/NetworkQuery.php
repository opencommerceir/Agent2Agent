<?php

namespace App\Domains\Nexus\Marketplace\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Growth\Infrastructure\Models\CoalitionMember;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation;
use Illuminate\Support\Collection;

/**
 * Phase 5/M4 — "Network Visualization": who a Business actually has a real
 * relationship with, and who its relationships are connected to. Same
 * "plain, autowired concrete Query class — not a Repository" convention
 * BusinessSearchQuery/RevenueQuery already established — read-only,
 * cross-domain (Business + Negotiation + Growth's Coalition) by design.
 *
 * "Relationship" here means a real, verifiable fact already recorded
 * elsewhere — an Accepted Negotiation, or shared Coalition membership —
 * never an invented affinity score (same honesty RankSuppliersAction's own
 * docblock already applies to ranking before Reputation, Phase 6, exists).
 */
class NetworkQuery
{
    /**
     * Distinct counterparties from every Accepted Negotiation the given
     * Business was a party to.
     *
     * @return list<int>
     */
    public function directPartners(int $businessId): array
    {
        $asInitiator = Negotiation::query()
            ->where('status', 'accepted')
            ->where('initiator_business_id', $businessId)
            ->pluck('counterparty_business_id');

        $asCounterparty = Negotiation::query()
            ->where('status', 'accepted')
            ->where('counterparty_business_id', $businessId)
            ->pluck('initiator_business_id');

        return $asInitiator->merge($asCounterparty)->unique()->values()->all();
    }

    /**
     * Distinct Businesses that share at least one Coalition membership with
     * the given Business (any status — forming a coalition together is
     * itself a real signal, independent of whether the bulk deal ever
     * closes).
     *
     * @return list<int>
     */
    public function coalitionMates(int $businessId): array
    {
        $coalitionIds = CoalitionMember::query()->where('business_id', $businessId)->pluck('coalition_id');

        if ($coalitionIds->isEmpty()) {
            return [];
        }

        return CoalitionMember::query()
            ->whereIn('coalition_id', $coalitionIds)
            ->where('business_id', '!=', $businessId)
            ->distinct()
            ->pluck('business_id')
            ->all();
    }

    /**
     * @param  list<int>  $businessIds
     * @return Collection<int, array{nameFa: string, nameEn: string, industry: string}>
     */
    public function summaries(array $businessIds): Collection
    {
        return Business::query()
            ->whereIn('id', $businessIds)
            ->get(['id', 'name_fa', 'name_en', 'industry'])
            ->keyBy('id')
            ->map(fn (Business $business) => [
                'nameFa' => $business->name_fa,
                'nameEn' => $business->name_en,
                'industry' => $business->industry,
            ]);
    }
}
