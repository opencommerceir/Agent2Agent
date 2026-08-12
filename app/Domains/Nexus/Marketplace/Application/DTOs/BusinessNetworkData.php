<?php

namespace App\Domains\Nexus\Marketplace\Application\DTOs;

/**
 * Read-model for the Network Visualization page and the
 * `nexus.network.get` MCP capability — nodes + edges, same "one DTO, two
 * consumers" shape ReferralStatusData/CoalitionData already use.
 *
 * `relation` on a node is relative to the viewing Business:
 * `self` (the caller), `direct` (a real Accepted-Negotiation counterparty),
 * `coalition` (shares Coalition membership, no negotiation yet), or
 * `recommended` (a direct partner's own direct partner — "businesses like
 * you also work with…", two hops away). `parentBusinessId` is only set on
 * `recommended` nodes — which direct partner introduced them, for drawing
 * the edge.
 */
final class BusinessNetworkData
{
    /**
     * @param  list<array{businessId: int, nameFa: string, nameEn: string, industry: string, relation: string, parentBusinessId: ?int}>  $nodes
     * @param  list<array{from: int, to: int, type: string}>  $edges
     */
    public function __construct(
        public readonly array $nodes,
        public readonly array $edges,
    ) {
    }

    public function toArray(): array
    {
        return [
            'nodes' => $this->nodes,
            'edges' => $this->edges,
        ];
    }
}
