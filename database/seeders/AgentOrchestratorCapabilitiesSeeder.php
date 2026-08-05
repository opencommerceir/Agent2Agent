<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Modules\AgentOrchestrator\Interfaces\MCP\AgentOrchestratorCapabilities;
use Illuminate\Database\Seeder;

/**
 * Registers the Agent Orchestrator module's capabilities into the
 * Capability Registry. Same seeder pattern every prior module's own
 * CapabilitiesSeeder established (see DemoCapabilitiesSeeder's docblock
 * for why this cannot safely live in AgentOrchestratorServiceProvider::boot()
 * instead — RefreshDatabase migrates *after* providers boot).
 *
 * Idempotent: safe to run against a database that already has these rows.
 */
class AgentOrchestratorCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (AgentOrchestratorCapabilities::definitions() as $definition) {
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
