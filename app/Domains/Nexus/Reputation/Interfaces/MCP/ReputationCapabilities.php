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
        ];
    }
}
