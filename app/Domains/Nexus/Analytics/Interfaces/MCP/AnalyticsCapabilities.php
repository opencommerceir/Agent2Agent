<?php

namespace App\Domains\Nexus\Analytics\Interfaces\MCP;

/**
 * Capability manifest for the Analytics domain (Phase 8) — same
 * manifest -> Seeder -> CapabilityHandlerRegistry split every earlier
 * domain's own MCP surface already established. Grows across Phase 8's
 * milestones (business analytics in M1, market intelligence in M2, deal
 * risk assessment in M5) the same way GrowthCapabilities grew one entry per
 * Phase 5 milestone.
 */
final class AnalyticsCapabilities
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
                'name' => 'nexus.analytics.business',
                'description' => "Get the calling Business's own deal success rate, savings from negotiating, and industry price benchmark",
                // Free, like nexus.credit.balance/nexus.reputation.score —
                // reading your own numbers must never itself be gated by
                // the CostGate (Phase 3/M2).
                'inputSchema' => [],
                'outputSchema' => [
                    'successRate' => 'number',
                    'completedDeals' => 'integer',
                    'dealCounts' => 'array',
                    'savings' => 'array',
                    'priceBenchmark' => 'array',
                ],
                'requiredPermissions' => ['nexus.analytics.read'],
            ],
        ];
    }
}
