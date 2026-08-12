<?php

namespace App\Domains\Nexus\Marketplace\Interfaces\MCP;

/**
 * The capability manifest for the Marketplace domain — what
 * NexusMarketplaceCapabilitiesSeeder registers into the Capability
 * Registry and NexusServiceProvider wires into CapabilityHandlerRegistry.
 * Same split Commerce's own CommerceCapabilities/CommerceCapabilitiesSeeder
 * pair established: plain data here, seeding idempotency plumbing
 * separate.
 */
final class MarketplaceCapabilities
{
    /**
     * @return list<array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, string>,
     *     outputSchema: array<string, string>,
     *     requiredPermissions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'nexus.marketplace.search',
                'description' => "Search other verified Businesses' catalogs by name or industry",
                // query/industry are both optional — same "declared fields
                // are always required, so leave optional ones out"
                // reasoning CommerceCapabilities' own manifest already
                // follows (MCPRequestValidationService has no "optional
                // but typed" yet).
                'inputSchema' => [],
                'outputSchema' => ['listings' => 'array'],
                'requiredPermissions' => ['nexus.marketplace.read'],
            ],
            [
                'name' => 'nexus.marketplace.network',
                'description' => "Get the calling Business's real relationship graph — direct Negotiation/Coalition partners, plus businesses those partners work with (Phase 5, Network Visualization)",
                // Free, like nexus.credit.balance/nexus.referral.status —
                // viewing your own relationship graph must never itself be
                // gated by the CostGate (Phase 3/M2).
                'inputSchema' => [],
                'outputSchema' => ['nodes' => 'array', 'edges' => 'array'],
                'requiredPermissions' => ['nexus.marketplace.read'],
            ],
        ];
    }
}
