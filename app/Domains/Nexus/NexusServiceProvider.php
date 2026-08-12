<?php

namespace App\Domains\Nexus;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Domains\Nexus\Agent\Application\Actions\ResolveActingBusinessAction;
use App\Domains\Nexus\Agent\Application\Listeners\CreateAgentOnBusinessVerifiedListener;
use App\Domains\Nexus\Agent\Domain\Repositories\AgentRepositoryInterface;
use App\Domains\Nexus\Agent\Infrastructure\Repositories\EloquentAgentRepository;
use App\Domains\Nexus\Business\Domain\Events\BusinessWasVerified;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Business\Infrastructure\Repositories\EloquentBusinessRepository;
use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Catalog\Infrastructure\Repositories\EloquentProductRepository;
use App\Domains\Nexus\Catalog\Infrastructure\Repositories\EloquentServiceRepository;
use App\Domains\Nexus\Marketplace\Application\Actions\SearchMarketplaceAction;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationMessageRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Repositories\NegotiationRepositoryInterface;
use App\Domains\Nexus\Negotiation\Infrastructure\Repositories\EloquentNegotiationMessageRepository;
use App\Domains\Nexus\Negotiation\Infrastructure\Repositories\EloquentNegotiationRepository;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registers Nexus's own infrastructure (config, routes, views, migrations)
 * plus each domain's repository bindings, the same "one provider per
 * module" shape Core's own CoreServiceProvider uses.
 */
class NexusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('nexus/platform.php'), 'nexus.platform');

        $this->app->bind(BusinessRepositoryInterface::class, EloquentBusinessRepository::class);
        $this->app->bind(AgentRepositoryInterface::class, EloquentAgentRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, EloquentServiceRepository::class);
        $this->app->bind(NegotiationRepositoryInterface::class, EloquentNegotiationRepository::class);
        $this->app->bind(NegotiationMessageRepositoryInterface::class, EloquentNegotiationMessageRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/nexus/web.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/api.php'));
        $this->loadRoutesFrom(base_path('routes/nexus/mcp.php'));

        $this->loadViewsFrom(resource_path('views/nexus'), 'nexus');
        $this->loadMigrationsFrom(database_path('migrations/nexus'));

        Event::listen(BusinessWasVerified::class, CreateAgentOnBusinessVerifiedListener::class);

        $this->registerMcpCapabilityHandlers();
    }

    /**
     * Wires each Nexus MCP capability's real handler into the Capability
     * Registry — the registry metadata itself is seeded separately
     * (database/seeders/Nexus*CapabilitiesSeeder.php), since
     * RefreshDatabase migrates *after* providers boot (same reasoning
     * CommerceServiceProvider's own wiring follows).
     */
    private function registerMcpCapabilityHandlers(): void
    {
        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register('nexus.marketplace.search', function (array $input, AuthContext $context) {
            $callingBusinessId = $this->app->make(ResolveActingBusinessAction::class)->execute($context->agentId);

            return $this->app->make(SearchMarketplaceAction::class)->execute(
                callingBusinessId: $callingBusinessId,
                query: $input['query'] ?? null,
                industry: $input['industry'] ?? null,
            );
        });
    }
}
