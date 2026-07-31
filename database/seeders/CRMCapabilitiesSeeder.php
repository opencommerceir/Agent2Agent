<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Modules\CRM\Interfaces\MCP\CRMCapabilities;
use Illuminate\Database\Seeder;

/**
 * Registers the CRM module's capabilities into the Capability Registry.
 * Follows the same seeder pattern Demo/CommerceCapabilitiesSeeder
 * established (see DemoCapabilitiesSeeder's docblock for why this cannot
 * safely live in CRMServiceProvider::boot() instead — RefreshDatabase
 * migrates *after* providers boot).
 *
 * Idempotent: safe to run against a database that already has these rows.
 */
class CRMCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (CRMCapabilities::definitions() as $definition) {
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
