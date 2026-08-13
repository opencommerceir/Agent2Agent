<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Domains\Nexus\Analytics\Interfaces\MCP\AnalyticsCapabilities;
use Illuminate\Database\Seeder;

/**
 * Registers the Nexus Analytics domain's capabilities (Phase 8) into the
 * Capability Registry. Same idempotent seeder pattern every earlier
 * Nexus*CapabilitiesSeeder already established. Named NexusAnalytics* (not
 * Analytics*) to stay distinct from the base platform's own, unrelated
 * AnalyticsCapabilitiesSeeder (App\Modules\Analytics).
 */
class NexusAnalyticsCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (AnalyticsCapabilities::definitions() as $definition) {
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
