<?php

namespace App\Domains\Nexus\Credit\Interfaces\MCP;

/**
 * The capability manifest for the Credit domain — what
 * NexusCreditCapabilitiesSeeder registers into the Capability Registry and
 * NexusServiceProvider wires into CapabilityHandlerRegistry. Same split
 * MarketplaceCapabilities/NexusMarketplaceCapabilitiesSeeder established.
 */
final class CreditCapabilities
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
                'name' => 'nexus.credit.balance',
                'description' => "Check the calling Business's current credit balance",
                // Deliberately free — an Agent checking its own balance
                // before deciding whether to propose/counter/accept must
                // never itself be gated by the CostGate (Phase 3/M2), or a
                // Business at exactly 0 credits could never even find out.
                'inputSchema' => [],
                'outputSchema' => ['balance' => 'integer'],
                'requiredPermissions' => ['nexus.credit.read'],
            ],
        ];
    }
}
