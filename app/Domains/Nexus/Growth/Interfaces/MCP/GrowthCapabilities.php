<?php

namespace App\Domains\Nexus\Growth\Interfaces\MCP;

/**
 * Capability manifest for the Growth domain (Phase 5) — same
 * manifest -> Seeder -> CapabilityHandlerRegistry split every earlier
 * domain's own MCP surface already established (CreditCapabilities,
 * MarketplaceCapabilities). Grows across Phase 5's milestones (referral in
 * M1, invite in M2, coalition in M3) the same way NexusServiceProvider's own
 * registerMcpCapabilityHandlers() grew one private method per milestone in
 * Phase 2/3.
 */
final class GrowthCapabilities
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
                'name' => 'nexus.referral.status',
                'description' => "Check the calling Business's own referral code, referral counts, and reward status",
                // Free, like nexus.credit.balance — checking your own
                // referral standing must never itself be gated by the
                // CostGate (Phase 3/M2).
                'inputSchema' => [],
                'outputSchema' => [
                    'code' => 'string|null',
                    'tier1Count' => 'integer',
                    'tier1RewardedCount' => 'integer',
                    'tier2Count' => 'integer',
                    'referrals' => 'array',
                ],
                'requiredPermissions' => ['nexus.growth.read'],
            ],
        ];
    }
}
