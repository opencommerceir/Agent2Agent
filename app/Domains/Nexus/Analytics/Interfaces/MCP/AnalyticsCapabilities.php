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
            [
                'name' => 'nexus.analytics.market',
                'description' => 'Get anonymized market intelligence for an industry (default: the calling Business\'s own) — price trend, demand signal, aggregate competitor stats',
                // industry is optional (defaults to the caller's own) —
                // same "declared fields are always required, so leave
                // optional ones out" rule nexus.marketplace.search's own
                // manifest already follows for its query/industry filters.
                'inputSchema' => [],
                'outputSchema' => [
                    'industry' => 'string',
                    'priceTrend' => 'array',
                    'demandSignal' => 'array',
                    'competitorStats' => 'array',
                ],
                'requiredPermissions' => ['nexus.analytics.read'],
            ],
            [
                'name' => 'nexus.analytics.forecast',
                'description' => "Forecast a Business's own reliability trend (improving/declining/stable) from real recent-vs-prior Negotiation outcomes, alongside its current Reputation score",
                // Free, same category as nexus.reputation.score — checking
                // any Business's public trust signal must never be gated
                // by the CostGate (Phase 3/M2).
                'inputSchema' => ['business_id' => 'integer'],
                'outputSchema' => [
                    'businessId' => 'integer',
                    'currentScore' => 'integer',
                    'trend' => 'string',
                    'recentSuccessRate' => 'number',
                    'priorSuccessRate' => 'number',
                ],
                'requiredPermissions' => ['nexus.analytics.read'],
            ],
            [
                'name' => 'nexus.analytics.risk',
                'description' => 'Rule-based 0-100 risk score for a deal of a given size with a specific counterparty (reputation, recent lost disputes, deal-size anomaly)',
                'inputSchema' => ['counterparty_business_id' => 'integer', 'deal_amount' => 'integer', 'currency' => 'string'],
                'outputSchema' => ['riskScore' => 'integer', 'riskLevel' => 'string', 'factors' => 'array'],
                'requiredPermissions' => ['nexus.analytics.read'],
            ],
            [
                'name' => 'nexus.analytics.scenario',
                'description' => "Estimate the likelihood a counterparty accepts a hypothetical unit price, from their own real acceptance-rate and price history",
                'inputSchema' => ['counterparty_business_id' => 'integer', 'catalog_item_type' => 'string', 'hypothetical_unit_amount' => 'integer'],
                'outputSchema' => ['estimatedAcceptanceLikelihood' => 'number', 'baselineAverageUnitAmount' => 'integer'],
                'requiredPermissions' => ['nexus.analytics.read'],
            ],
        ];
    }
}
