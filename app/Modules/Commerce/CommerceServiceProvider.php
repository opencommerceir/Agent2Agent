<?php

namespace App\Modules\Commerce;

use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Commerce module. Commerce is the first Domain Module
 * (Decision 004) — everything it needs is self-contained here; nothing in
 * Core changed to make this module possible (Decision 005).
 */
class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->app->make(ConnectorRegistry::class);
        $registry->registerProductConnector('mock', new MockProductConnector());
    }
}
