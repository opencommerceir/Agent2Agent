<?php

namespace App\Modules\Commerce;

use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Commerce module. Commerce is the first Domain Module
 * (Decision 004) — everything it needs is self-contained here; nothing in
 * Core changed to make this module possible (Decision 005), aside from
 * Phase 2's deliberate widening of CapabilityHandlerRegistry's handler
 * signature to carry tenantId (see that class's docblock).
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows Demo's
 * seeder pattern instead (CommerceCapabilitiesSeeder) for the same
 * RefreshDatabase-ordering reason documented there.
 */
class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class);

        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
    }

    public function boot(): void
    {
        $connectors = $this->app->make(ConnectorRegistry::class);
        $connectors->registerProductConnector('mock', new MockProductConnector());

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);
        $handlers->register(
            'commerce.product.search',
            fn (array $input, int $tenantId) => $this->app->make(ListProductsAction::class)->execute($input, $tenantId),
        );
    }
}
