<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Domains\Nexus\PrivateMarketplace\Interfaces\MCP\PrivateMarketplaceCapabilities;
use Illuminate\Database\Seeder;

/**
 * Same idempotent seeder pattern as NexusMarketplaceCapabilitiesSeeder.
 */
class NexusPrivateMarketplaceCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (PrivateMarketplaceCapabilities::definitions() as $definition) {
            try {
                $getCapability->execute($definition['name']);

                continue;
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
