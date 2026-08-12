<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Marketplace\Application\DTOs\BusinessNetworkData;
use App\Domains\Nexus\Marketplace\Infrastructure\Queries\NetworkQuery;

/**
 * Phase 5/M4 — "Network Visualization" / "Businesses like you also work
 * with…". Builds a small graph around the calling Business: its direct
 * relationships (Accepted Negotiations, shared Coalitions) plus one hop
 * further — the direct partners' own direct partners, capped, to keep the
 * graph readable and the query bounded rather than walking the whole
 * platform's negotiation graph.
 */
final class GetBusinessNetworkAction
{
    private const MAX_PARTNERS_EXPANDED = 5;

    private const MAX_RECOMMENDED = 10;

    public function __construct(
        private readonly NetworkQuery $query,
    ) {
    }

    public function execute(int $businessId): BusinessNetworkData
    {
        $directPartnerIds = $this->query->directPartners($businessId);
        $coalitionMateIds = array_values(array_diff($this->query->coalitionMates($businessId), $directPartnerIds));

        $recommended = []; // businessId => parentBusinessId
        $excluded = array_merge([$businessId], $directPartnerIds, $coalitionMateIds);

        foreach (array_slice($directPartnerIds, 0, self::MAX_PARTNERS_EXPANDED) as $partnerId) {
            foreach ($this->query->directPartners($partnerId) as $secondHopId) {
                if (in_array($secondHopId, $excluded, true) || array_key_exists($secondHopId, $recommended)) {
                    continue;
                }

                $recommended[$secondHopId] = $partnerId;

                if (count($recommended) >= self::MAX_RECOMMENDED) {
                    break 2;
                }
            }
        }

        $allIds = array_merge([$businessId], $directPartnerIds, $coalitionMateIds, array_keys($recommended));
        $summaries = $this->query->summaries($allIds);

        $nodes = [];
        $nodes[] = $this->node($businessId, 'self', null, $summaries);

        foreach ($directPartnerIds as $id) {
            $nodes[] = $this->node($id, 'direct', null, $summaries);
        }

        foreach ($coalitionMateIds as $id) {
            $nodes[] = $this->node($id, 'coalition', null, $summaries);
        }

        foreach ($recommended as $id => $parentId) {
            $nodes[] = $this->node($id, 'recommended', $parentId, $summaries);
        }

        $edges = [];

        foreach ($directPartnerIds as $id) {
            $edges[] = ['from' => $businessId, 'to' => $id, 'type' => 'negotiated'];
        }

        foreach ($coalitionMateIds as $id) {
            $edges[] = ['from' => $businessId, 'to' => $id, 'type' => 'coalition'];
        }

        foreach ($recommended as $id => $parentId) {
            $edges[] = ['from' => $parentId, 'to' => $id, 'type' => 'recommended'];
        }

        return new BusinessNetworkData($nodes, $edges);
    }

    private function node(int $id, string $relation, ?int $parentBusinessId, $summaries): array
    {
        $summary = $summaries->get($id);

        return [
            'businessId' => $id,
            'nameFa' => $summary['nameFa'] ?? '',
            'nameEn' => $summary['nameEn'] ?? '',
            'industry' => $summary['industry'] ?? '',
            'relation' => $relation,
            'parentBusinessId' => $parentBusinessId,
        ];
    }
}
