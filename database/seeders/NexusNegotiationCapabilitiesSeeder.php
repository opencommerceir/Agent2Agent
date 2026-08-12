<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Domains\Nexus\Negotiation\Interfaces\MCP\NegotiationCapabilities;
use Illuminate\Database\Seeder;

/**
 * Registers the Negotiation domain's capabilities into the Capability
 * Registry. Follows the same seeder pattern CommerceCapabilitiesSeeder/
 * NexusMarketplaceCapabilitiesSeeder established.
 *
 * Idempotent: safe to run against a database that already has these rows.
 */
class NexusNegotiationCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (NegotiationCapabilities::definitions() as $definition) {
            try {
                $getCapability->execute($definition['name']);

                continue; // already registered
            } catch (CapabilityNotFoundException) {
                // not registered yet — fall through and register it
            }

            $registerCapability->execute(
                name: $definition['name'],
                description: $definition['description'],
                inputSchema: $definition['inputSchema'],
                outputSchema: $definition['outputSchema'],
                requiredPermissions: $definition['requiredPermissions'],
            );
        }
    }
}
