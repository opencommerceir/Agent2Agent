<?php

namespace App\Domains\Nexus\Negotiation\Interfaces\MCP;

/**
 * The capability manifest for the Negotiation domain — what
 * NexusNegotiationCapabilitiesSeeder registers into the Capability
 * Registry and NexusServiceProvider wires into CapabilityHandlerRegistry.
 * Same split Commerce's own CommerceCapabilities/CommerceCapabilitiesSeeder
 * pair established.
 */
final class NegotiationCapabilities
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
                'name' => 'nexus.negotiation.propose',
                'description' => 'Open a Negotiation with another Business over one catalog item',
                // quantity/notes are optional — same "declared fields are
                // always required, so leave optional ones out" reasoning
                // CommerceCapabilities' own manifest already follows.
                'inputSchema' => [
                    'counterparty_business_id' => 'integer',
                    'catalog_item_type' => 'string',
                    'catalog_item_id' => 'integer',
                    'price_amount' => 'integer',
                    'price_currency' => 'string',
                ],
                'outputSchema' => ['negotiation' => 'array'],
                'requiredPermissions' => ['nexus.negotiation.manage'],
            ],
            [
                'name' => 'nexus.negotiation.counter',
                'description' => 'Send a counter-offer on an open Negotiation',
                'inputSchema' => ['negotiation_id' => 'integer', 'price_amount' => 'integer', 'price_currency' => 'string'],
                'outputSchema' => ['negotiation' => 'array'],
                'requiredPermissions' => ['nexus.negotiation.manage'],
            ],
            [
                'name' => 'nexus.negotiation.accept',
                'description' => "Accept the Negotiation's current terms — may pause for human approval if it exceeds the accepting Agent's authority_limits",
                'inputSchema' => ['negotiation_id' => 'integer'],
                'outputSchema' => ['negotiation' => 'array'],
                'requiredPermissions' => ['nexus.negotiation.manage'],
            ],
            [
                'name' => 'nexus.negotiation.reject',
                'description' => 'Reject a Negotiation',
                'inputSchema' => ['negotiation_id' => 'integer'],
                'outputSchema' => ['negotiation' => 'array'],
                'requiredPermissions' => ['nexus.negotiation.manage'],
            ],
            [
                'name' => 'nexus.negotiation.status',
                'description' => "Get a Negotiation's current status and terms",
                'inputSchema' => ['negotiation_id' => 'integer'],
                'outputSchema' => ['negotiation' => 'array'],
                'requiredPermissions' => ['nexus.negotiation.read'],
            ],
        ];
    }
}
