<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Domains\Nexus\Credit\Interfaces\MCP\CreditCapabilities;
use Illuminate\Database\Seeder;

/**
 * Registers the Credit domain's capabilities into the Capability Registry.
 * Same seeder pattern NexusMarketplaceCapabilitiesSeeder established —
 * cannot safely live in NexusServiceProvider::boot() instead, since
 * RefreshDatabase migrates *after* providers boot.
 *
 * Idempotent: safe to run against a database that already has these rows.
 */
class NexusCreditCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (CreditCapabilities::definitions() as $definition) {
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
