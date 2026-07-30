<?php

namespace Database\Seeders;

use App\Core\Application\Actions\GetCapabilityAction;
use App\Core\Application\Actions\RegisterCapabilityAction;
use App\Core\Domain\Exceptions\CapabilityNotFoundException;
use App\Modules\Demo\Interfaces\MCP\DemoCapabilities;
use Illuminate\Database\Seeder;

/**
 * Registers the Demo module's capabilities into the Capability Registry.
 *
 * Deliberately NOT done in DemoServiceProvider::boot(): ServiceProviders'
 * boot() runs during application bootstrap, which — under Laravel's
 * RefreshDatabase test trait — completes *before* migrations create the
 * `capabilities` table (refreshApplication() boots every provider, then
 * setUpTraits() runs RefreshDatabase's migration step afterward). A
 * DB-querying boot() breaks every test with "no such table: capabilities".
 * A seeder is the correct place for "insert reference data once tables
 * exist" — run explicitly after migrating, in both real deployments
 * (`php artisan db:seed`) and tests (`$this->seed(DemoCapabilitiesSeeder::class)`,
 * called only where a test actually needs Demo capabilities registered).
 *
 * Idempotent: safe to run against a database that already has these rows.
 */
class DemoCapabilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $registerCapability = app(RegisterCapabilityAction::class);
        $getCapability = app(GetCapabilityAction::class);

        foreach (DemoCapabilities::definitions() as $definition) {
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
