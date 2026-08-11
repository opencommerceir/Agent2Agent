<?php

namespace App\Domains\Nexus;

use Illuminate\Support\ServiceProvider;

/**
 * Registers Nexus's own infrastructure (config, routes, views, migrations).
 * Phase 0 only — no domain bindings yet, those land as each domain under
 * app/Domains/Nexus/* gets real Application/Infrastructure code.
 */
class NexusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('nexus/platform.php'), 'nexus.platform');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/nexus/web.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/api.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/mcp.php'));

        $this->loadViewsFrom(resource_path('views/nexus'), 'nexus');
        $this->loadMigrationsFrom(database_path('migrations/nexus'));
    }
}
