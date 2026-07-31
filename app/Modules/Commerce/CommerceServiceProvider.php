<?php

namespace App\Modules\Commerce;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\ApplyCouponAction;
use App\Modules\Commerce\Application\Actions\CalculatePricingAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\GetCartAction;
use App\Modules\Commerce\Application\Actions\GetCustomerAction;
use App\Modules\Commerce\Application\Actions\GetOrderAction;
use App\Modules\Commerce\Application\Actions\GetWooCommerceProductAction;
use App\Modules\Commerce\Application\Actions\ListCustomersAction;
use App\Modules\Commerce\Application\Actions\ListOrdersAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Application\Actions\RefundPaymentAction;
use App\Modules\Commerce\Application\Actions\SyncWooCommerceProductsAction;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Application\DTOs\CouponData;
use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Application\DTOs\PricingData;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Application\Services\MockPaymentGateway;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\WooCommerceClient;
use App\Modules\Commerce\Application\Services\WooCommerceClientInterface;
use App\Modules\Commerce\Application\Services\WooCommerceConfig;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Services\WooCommerceProductMapper;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use App\Modules\Commerce\Infrastructure\Connectors\WooCommerceProductConnector;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCartRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCouponRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCustomerRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentDiscountRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentInventoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentOrderRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentPaymentRepository;
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
        $this->app->bind(PaymentRepositoryInterface::class, EloquentPaymentRepository::class);
        $this->app->bind(CouponRepositoryInterface::class, EloquentCouponRepository::class);
        $this->app->bind(DiscountRepositoryInterface::class, EloquentDiscountRepository::class);
        $this->app->bind(PaymentGatewayInterface::class, MockPaymentGateway::class);

        $this->app->bind(
            WooCommerceClientInterface::class,
            fn () => new WooCommerceClient(WooCommerceConfig::fromConfig()),
        );
    }

    public function boot(): void
    {
        $connectors = $this->app->make(ConnectorRegistry::class);
        $connectors->registerProductConnector('mock', new MockProductConnector());
        $connectors->registerProductConnector('woocommerce', new WooCommerceProductConnector(
            $this->app->make(WooCommerceClientInterface::class),
            new WooCommerceProductMapper(),
            WooCommerceConfig::fromConfig()->currency,
        ));

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

        $handlers->register('commerce.checkout.calculate', function (array $input, AuthContext $context) {
            /** @var PricingData $pricing */
            $pricing = $this->app->make(CalculatePricingAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                cartId: (int) $input['cart_id'],
                couponCode: $input['coupon_code'] ?? null,
            );

            return ['pricing' => $pricing->toArray()];
        });

        $handlers->register('commerce.checkout.process', function (array $input, AuthContext $context) {
            /** @var array{order: OrderData, payment: PaymentData} $result */
            $result = $this->app->make(ProcessPaymentAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                cartId: (int) $input['cart_id'],
                paymentMethod: $input['payment_method'],
                paymentDetails: $input['payment_details'] ?? [],
                couponCode: $input['coupon_code'] ?? null,
                notes: $input['notes'] ?? null,
                customerId: isset($input['customer_id']) ? (int) $input['customer_id'] : null,
            );

            return ['order' => $result['order']->toArray(), 'payment' => $result['payment']->toArray()];
        });

        $handlers->register('commerce.payment.refund', function (array $input, AuthContext $context) {
            /** @var PaymentData $payment */
            $payment = $this->app->make(RefundPaymentAction::class)->execute(
                paymentId: (int) $input['payment_id'],
                tenantId: $context->tenantId,
                reason: $input['reason'] ?? null,
            );

            return ['payment' => $payment->toArray(), 'message' => 'Payment refunded.'];
        });

        $handlers->register('commerce.coupon.create', function (array $input, AuthContext $context) {
            /** @var CouponData $coupon */
            $coupon = $this->app->make(CreateCouponAction::class)->execute(
                tenantId: $context->tenantId,
                code: $input['code'],
                discountType: $input['discount_type'],
                discountValue: (int) $input['discount_value'],
                minOrderAmount: isset($input['min_order_amount']) ? (int) $input['min_order_amount'] : null,
                maxUses: isset($input['max_uses']) ? (int) $input['max_uses'] : null,
                expiresAt: $input['expires_at'] ?? null,
            );

            return ['coupon' => $coupon->toArray()];
        });

        $handlers->register('commerce.woocommerce.sync', function (array $input, AuthContext $context) {
            $result = $this->app->make(SyncWooCommerceProductsAction::class)->execute(
                tenantId: $context->tenantId,
                page: isset($input['page']) ? (int) $input['page'] : 1,
                limit: isset($input['limit']) ? (int) $input['limit'] : 20,
            );

            return ['result' => $result->toArray()];
        });

        $handlers->register('commerce.woocommerce.get', function (array $input, AuthContext $context) {
            $product = $this->app->make(GetWooCommerceProductAction::class)->execute($input['external_id']);

            return ['product' => $product->toArray()];
        });
    }
}
