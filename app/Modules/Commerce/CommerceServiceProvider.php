<?php

namespace App\Modules\Commerce;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\GetCartAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCartRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentInventoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the Commerce module. Commerce is the first Domain Module
 * (Decision 004) — everything it needs is self-contained here; nothing in
 * Core changed to make this module possible (Decision 005), aside from
 * Phase 2's deliberate widening of CapabilityHandlerRegistry's handler
 * signature — first to carry tenantId, then to carry the fuller
 * AuthContext once Cart ownership needed the calling Agent's own id too
 * (see that class's docblock).
 *
 * Capability *handler* registration lives here (pure in-memory, safe on
 * every boot); capability *description* registration follows Demo's
 * seeder pattern instead (CommerceCapabilitiesSeeder) for the same
 * RefreshDatabase-ordering reason documented there.
 *
 * Every Cart capability hardcodes MemberType::Agent as the cart owner —
 * same reasoning MCPGatewayController's docblock gives for hardcoding it
 * throughout: MCP is the AI Agent entry point specifically, and the
 * User identity path remains incomplete (HANDOFF tech debt #4).
 */
class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class);

        $this->app->bind(ProductRepositoryInterface::class, EloquentProductRepository::class);
        $this->app->bind(CategoryRepositoryInterface::class, EloquentCategoryRepository::class);
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(InventoryRepositoryInterface::class, EloquentInventoryRepository::class);
    }

    public function boot(): void
    {
        $connectors = $this->app->make(ConnectorRegistry::class);
        $connectors->registerProductConnector('mock', new MockProductConnector());

        $handlers = $this->app->make(CapabilityHandlerRegistry::class);

        $handlers->register(
            'commerce.product.search',
            fn (array $input, AuthContext $context) => $this->app->make(ListProductsAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('commerce.cart.add', function (array $input, AuthContext $context) {
            $cart = $this->app->make(AddToCartAction::class)->execute(
                tenantId: $context->tenantId,
                ownerType: MemberType::Agent,
                ownerId: $context->agentId,
                productId: (int) $input['product_id'],
                quantity: (int) $input['quantity'],
            );

            return ['cart' => $cart->toArray(), 'message' => 'Product added to cart.'];
        });

        $handlers->register('commerce.cart.get', function (array $input, AuthContext $context) {
            /** @var CartData $cart */
            $cart = $this->app->make(GetCartAction::class)->execute($context->tenantId, MemberType::Agent, $context->agentId);

            return ['cart' => $cart->toArray()];
        });
    }
}
