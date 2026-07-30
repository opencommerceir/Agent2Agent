<?php

namespace App\Modules\Commerce;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\GetCartAction;
use App\Modules\Commerce\Application\Actions\GetCustomerAction;
use App\Modules\Commerce\Application\Actions\GetOrderAction;
use App\Modules\Commerce\Application\Actions\ListCustomersAction;
use App\Modules\Commerce\Application\Actions\ListOrdersAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCartRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCustomerRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentInventoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentOrderRepository;
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
        $this->app->bind(OrderRepositoryInterface::class, EloquentOrderRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, EloquentCustomerRepository::class);
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

        $handlers->register('commerce.order.place', function (array $input, AuthContext $context) {
            /** @var OrderData $order */
            $order = $this->app->make(PlaceOrderAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                cartId: (int) $input['cart_id'],
                notes: $input['notes'] ?? null,
                customerId: isset($input['customer_id']) ? (int) $input['customer_id'] : null,
            );

            return ['order' => $order->toArray()];
        });

        $handlers->register('commerce.order.get', function (array $input, AuthContext $context) {
            /** @var OrderData $order */
            $order = $this->app->make(GetOrderAction::class)->execute((int) $input['order_id'], $context->tenantId);

            return ['order' => $order->toArray()];
        });

        $handlers->register(
            'commerce.order.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListOrdersAction::class)->execute($input, $context->tenantId),
        );

        $handlers->register('commerce.customer.create', function (array $input, AuthContext $context) {
            /** @var CustomerData $customer */
            $customer = $this->app->make(CreateCustomerAction::class)->execute(
                tenantId: $context->tenantId,
                firstName: $input['first_name'],
                lastName: $input['last_name'],
                email: $input['email'],
                phone: $input['phone'] ?? null,
                address: $input['address'] ?? null,
            );

            return ['customer' => $customer->toArray()];
        });

        $handlers->register('commerce.customer.get', function (array $input, AuthContext $context) {
            /** @var CustomerData $customer */
            $customer = $this->app->make(GetCustomerAction::class)->execute((int) $input['customer_id'], $context->tenantId);

            return ['customer' => $customer->toArray()];
        });

        $handlers->register(
            'commerce.customer.list',
            fn (array $input, AuthContext $context) => $this->app->make(ListCustomersAction::class)->execute($input, $context->tenantId),
        );
    }
}
