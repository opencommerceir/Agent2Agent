<?php

namespace App\Domains\Nexus\Reputation\Interfaces\MCP;

/**
 * The capability manifest for the Reputation domain (Phase 6) — same
 * plain-data/seeding-plumbing split every other *Capabilities class in
 * this codebase follows.
 */
final class ReputationCapabilities
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
                'name' => 'nexus.review.submit',
                'description' => 'Rate and review the counterparty on a Negotiation whose Escrow has been released (a genuinely completed deal)',
                'inputSchema' => ['negotiation_id' => 'integer', 'rating' => 'integer', 'comment' => 'string'],
                'outputSchema' => ['review' => 'array'],
                'requiredPermissions' => ['nexus.reputation.manage'],
            ],
            [
                'name' => 'nexus.review.list',
                'description' => 'List published reviews received by a Business',
                'inputSchema' => ['business_id' => 'integer'],
                'outputSchema' => ['reviews' => 'array'],
                'requiredPermissions' => ['nexus.reputation.read'],
            ],
            [
                'name' => 'nexus.reputation.score',
                'description' => "Get a Business's computed reputation score (0-1000), badges, and component breakdown — check this before negotiating with an unfamiliar counterparty",
                'inputSchema' => ['business_id' => 'integer'],
                'outputSchema' => ['score' => 'array'],
                // Free, like nexus.credit.balance/nexus.marketplace.network —
                // checking trust signals before deciding whether to deal
                // with someone must never itself be gated by the CostGate.
                'requiredPermissions' => ['nexus.reputation.read'],
            ],
        ];
    }
}
