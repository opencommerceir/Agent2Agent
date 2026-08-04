<?php

namespace App\Modules\Commerce;

use App\Core\Application\DTOs\AuthContext;
use App\Core\Application\Services\CapabilityHandlerRegistry;
use App\Core\Domain\ValueObjects\MemberType;
use App\Modules\Commerce\Application\Actions\AddToCartAction;
use App\Modules\Commerce\Application\Actions\ApplyCouponAction;
use App\Modules\Commerce\Application\Actions\ApplyDiscountsToCartAction;
use App\Modules\Commerce\Application\Actions\ApproveWarehouseTransferAction;
use App\Modules\Commerce\Application\Actions\BulkInventoryUpdateAction;
use App\Modules\Commerce\Application\Actions\BulkPriceUpdateAction;
use App\Modules\Commerce\Application\Actions\BulkStatusUpdateAction;
use App\Modules\Commerce\Application\Actions\CalculatePricingAction;
use App\Modules\Commerce\Application\Actions\CompleteWarehouseTransferAction;
use App\Modules\Commerce\Application\Actions\CreateCouponAction;
use App\Modules\Commerce\Application\Actions\CreateCustomerAction;
use App\Modules\Commerce\Application\Actions\CreateDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\CreateProductVariantAction;
use App\Modules\Commerce\Application\Actions\CreateVariantAttributeAction;
use App\Modules\Commerce\Application\Actions\CreateWarehouseAction;
use App\Modules\Commerce\Application\Actions\DeleteDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\DeleteProductVariantAction;
use App\Modules\Commerce\Application\Actions\ExportOrdersAction;
use App\Modules\Commerce\Application\Actions\FindNearestWarehouseAction;
use App\Modules\Commerce\Application\Actions\GenerateVariantCombinationsAction;
use App\Modules\Commerce\Application\Actions\GetAvailableDiscountsAction;
use App\Modules\Commerce\Application\Actions\GetBulkOperationAction;
use App\Modules\Commerce\Application\Actions\GetCartAction;
use App\Modules\Commerce\Application\Actions\GetCustomerAction;
use App\Modules\Commerce\Application\Actions\GetDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\GetOrderAction;
use App\Modules\Commerce\Application\Actions\GetProductVariantAction;
use App\Modules\Commerce\Application\Actions\GetWarehouseAction;
use App\Modules\Commerce\Application\Actions\GetWarehouseStockAction;
use App\Modules\Commerce\Application\Actions\GetWooCommerceProductAction;
use App\Modules\Commerce\Application\Actions\ImportCustomersAction;
use App\Modules\Commerce\Application\Actions\ImportProductsAction;
use App\Modules\Commerce\Application\Actions\ListBulkOperationsAction;
use App\Modules\Commerce\Application\Actions\ListCustomersAction;
use App\Modules\Commerce\Application\Actions\ListDiscountRulesAction;
use App\Modules\Commerce\Application\Actions\ListOrdersAction;
use App\Modules\Commerce\Application\Actions\ListProductsAction;
use App\Modules\Commerce\Application\Actions\ListProductVariantsAction;
use App\Modules\Commerce\Application\Actions\ListVariantAttributesAction;
use App\Modules\Commerce\Application\Actions\ListWarehousesAction;
use App\Modules\Commerce\Application\Actions\PlaceOrderAction;
use App\Modules\Commerce\Application\Actions\ProcessPaymentAction;
use App\Modules\Commerce\Application\Actions\RefundPaymentAction;
use App\Modules\Commerce\Application\Actions\RequestWarehouseTransferAction;
use App\Modules\Commerce\Application\Actions\SyncWooCommerceProductsAction;
use App\Modules\Commerce\Application\Actions\UpdateDiscountRuleAction;
use App\Modules\Commerce\Application\Actions\UpdateProductVariantAction;
use App\Modules\Commerce\Application\Actions\UpdateWarehouseAction;
use App\Modules\Commerce\Application\DTOs\BulkOperationData;
use App\Modules\Commerce\Application\DTOs\CartData;
use App\Modules\Commerce\Application\DTOs\CouponData;
use App\Modules\Commerce\Application\DTOs\CustomerData;
use App\Modules\Commerce\Application\DTOs\DiscountRuleData;
use App\Modules\Commerce\Application\DTOs\OrderData;
use App\Modules\Commerce\Application\DTOs\PaymentData;
use App\Modules\Commerce\Application\DTOs\PricingData;
use App\Modules\Commerce\Application\DTOs\ProductVariantData;
use App\Modules\Commerce\Application\DTOs\VariantAttributeData;
use App\Modules\Commerce\Application\DTOs\WarehouseData;
use App\Modules\Commerce\Application\DTOs\WarehouseTransferData;
use App\Modules\Commerce\Application\Services\CsvParser;
use App\Modules\Commerce\Application\Services\CsvValidator;
use App\Modules\Commerce\Domain\Services\CsvParserInterface;
use App\Modules\Commerce\Domain\Services\CsvValidatorInterface;
use App\Modules\Commerce\Application\Services\ConnectorRegistry;
use App\Modules\Commerce\Application\Services\MockPaymentGateway;
use App\Modules\Commerce\Application\Services\NullTaxRateProvider;
use App\Modules\Commerce\Application\Services\PaymentGatewayInterface;
use App\Modules\Commerce\Application\Services\TaxRateProviderInterface;
use App\Modules\Commerce\Application\Services\WooCommerceClient;
use App\Modules\Commerce\Application\Services\WooCommerceClientInterface;
use App\Modules\Commerce\Application\Services\WooCommerceConfig;
use App\Modules\Commerce\Domain\Repositories\AppliedDiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CartRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CouponRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\CustomerRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\DiscountRuleRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\InventoryRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\OrderRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\PaymentRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\BulkOperationRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\VariantAttributeRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseRepositoryInterface;
use App\Modules\Commerce\Domain\Repositories\WarehouseTransferRepositoryInterface;
use App\Modules\Commerce\Domain\Services\WooCommerceProductMapper;
use App\Modules\Commerce\Infrastructure\Connectors\MockProductConnector;
use App\Modules\Commerce\Infrastructure\Connectors\WooCommerceProductConnector;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentAppliedDiscountRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCartRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCouponRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentCustomerRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentDiscountRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentDiscountRuleRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentInventoryRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentOrderRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentProductRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentProductVariantRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentBulkOperationRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentVariantAttributeRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentWarehouseRepository;
use App\Modules\Commerce\Infrastructure\Repositories\EloquentWarehouseTransferRepository;
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
        $this->app->bind(ProductVariantRepositoryInterface::class, EloquentProductVariantRepository::class);
        $this->app->bind(VariantAttributeRepositoryInterface::class, EloquentVariantAttributeRepository::class);
        $this->app->bind(WarehouseRepositoryInterface::class, EloquentWarehouseRepository::class);
        $this->app->bind(WarehouseTransferRepositoryInterface::class, EloquentWarehouseTransferRepository::class);
        $this->app->bind(BulkOperationRepositoryInterface::class, EloquentBulkOperationRepository::class);
        $this->app->bind(DiscountRuleRepositoryInterface::class, EloquentDiscountRuleRepository::class);
        $this->app->bind(AppliedDiscountRepositoryInterface::class, EloquentAppliedDiscountRepository::class);
        $this->app->bind(CsvParserInterface::class, CsvParser::class);
        $this->app->bind(CsvValidatorInterface::class, CsvValidator::class);
        $this->app->bind(PaymentGatewayInterface::class, MockPaymentGateway::class);

        $this->app->bind(
            WooCommerceClientInterface::class,
            fn () => new WooCommerceClient(WooCommerceConfig::fromConfig()),
        );

        // Overridden by FinanceServiceProvider (which registers after this
        // one in bootstrap/providers.php) when the Finance module is
        // loaded — see NullTaxRateProvider's own docblock.
        $this->app->bind(TaxRateProviderInterface::class, NullTaxRateProvider::class);
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
                variantId: isset($input['variant_id']) ? (int) $input['variant_id'] : null,
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
                region: $input['region'] ?? null,
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
                region: $input['region'] ?? null,
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
                discountRuleId: isset($input['discount_rule_id']) ? (int) $input['discount_rule_id'] : null,
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

        // Phase 5, Stage 1 (Product Variants, §7.21). Three of the
        // request's own 8 capability names hit the usual 3-dot-segment
        // gotcha (HANDOFF §3 pattern #13):
        // commerce.variant.attribute.create/.list (4 segments) renamed to
        // commerce.attribute.create/.list, and
        // commerce.variant.combinations.generate (4 segments) renamed to
        // commerce.variant.generate.

        $handlers->register('commerce.attribute.create', function (array $input, AuthContext $context) {
            /** @var VariantAttributeData $attribute */
            $attribute = $this->app->make(CreateVariantAttributeAction::class)->execute(
                tenantId: $context->tenantId,
                name: $input['name'],
                values: $input['values'],
                displayOrder: isset($input['display_order']) ? (int) $input['display_order'] : 0,
            );

            return ['attribute' => $attribute->toArray()];
        });

        $handlers->register(
            'commerce.attribute.list',
            fn (array $input, AuthContext $context) => [
                'attributes' => array_map(
                    fn (VariantAttributeData $attribute) => $attribute->toArray(),
                    $this->app->make(ListVariantAttributesAction::class)->execute($context->tenantId),
                ),
            ],
        );

        $handlers->register('commerce.variant.create', function (array $input, AuthContext $context) {
            /** @var ProductVariantData $variant */
            $variant = $this->app->make(CreateProductVariantAction::class)->execute(
                tenantId: $context->tenantId,
                productId: (int) $input['product_id'],
                attributes: $input['attributes'],
                priceAmount: (int) $input['price_amount'],
                priceCurrency: $input['price_currency'],
                imageUrl: $input['image_url'] ?? null,
                initialStock: isset($input['initial_stock']) ? (int) $input['initial_stock'] : 0,
            );

            return ['variant' => $variant->toArray()];
        });

        $handlers->register('commerce.variant.update', function (array $input, AuthContext $context) {
            /** @var ProductVariantData $variant */
            $variant = $this->app->make(UpdateProductVariantAction::class)->execute(
                id: (int) $input['variant_id'],
                tenantId: $context->tenantId,
                priceAmount: (int) $input['price_amount'],
                priceCurrency: $input['price_currency'],
                imageUrl: $input['image_url'] ?? null,
                isActive: (bool) ($input['is_active'] ?? true),
                stockQuantity: isset($input['stock_quantity']) ? (int) $input['stock_quantity'] : null,
            );

            return ['variant' => $variant->toArray()];
        });

        $handlers->register('commerce.variant.delete', function (array $input, AuthContext $context) {
            $this->app->make(DeleteProductVariantAction::class)->execute((int) $input['variant_id'], $context->tenantId);

            return ['message' => 'Variant deleted.'];
        });

        $handlers->register('commerce.variant.get', function (array $input, AuthContext $context) {
            /** @var ProductVariantData $variant */
            $variant = $this->app->make(GetProductVariantAction::class)->execute((int) $input['variant_id'], $context->tenantId);

            return ['variant' => $variant->toArray()];
        });

        $handlers->register(
            'commerce.variant.list',
            fn (array $input, AuthContext $context) => [
                'variants' => array_map(
                    fn (ProductVariantData $variant) => $variant->toArray(),
                    $this->app->make(ListProductVariantsAction::class)->execute((int) $input['product_id'], $context->tenantId),
                ),
            ],
        );

        $handlers->register('commerce.variant.generate', function (array $input, AuthContext $context) {
            $variants = $this->app->make(GenerateVariantCombinationsAction::class)->execute(
                tenantId: $context->tenantId,
                productId: (int) $input['product_id'],
                attributeIds: array_map(intval(...), $input['attribute_ids']),
                priceAmount: (int) $input['price_amount'],
                priceCurrency: $input['price_currency'],
            );

            return [
                'variants' => array_map(fn (ProductVariantData $variant) => $variant->toArray(), $variants),
                'count' => count($variants),
            ];
        });

        // Phase 5, Stage 2 (Multi-warehouse Inventory, §7.22). See
        // CommerceCapabilities' own docblock for the 5 capability renames
        // this stage needed (the recurring 3-dot-segment gotcha).

        $handlers->register('commerce.warehouse.create', function (array $input, AuthContext $context) {
            /** @var WarehouseData $warehouse */
            $warehouse = $this->app->make(CreateWarehouseAction::class)->execute(
                tenantId: $context->tenantId,
                code: $input['code'],
                name: $input['name'],
                latitude: (float) $input['latitude'],
                longitude: (float) $input['longitude'],
                address: $input['address'],
            );

            return ['warehouse' => $warehouse->toArray()];
        });

        $handlers->register('commerce.warehouse.update', function (array $input, AuthContext $context) {
            /** @var WarehouseData $warehouse */
            $warehouse = $this->app->make(UpdateWarehouseAction::class)->execute(
                id: (int) $input['warehouse_id'],
                tenantId: $context->tenantId,
                name: $input['name'],
                latitude: (float) $input['latitude'],
                longitude: (float) $input['longitude'],
                address: $input['address'],
                isActive: (bool) ($input['is_active'] ?? true),
            );

            return ['warehouse' => $warehouse->toArray()];
        });

        $handlers->register('commerce.warehouse.get', function (array $input, AuthContext $context) {
            /** @var WarehouseData $warehouse */
            $warehouse = $this->app->make(GetWarehouseAction::class)->execute((int) $input['warehouse_id'], $context->tenantId);

            return ['warehouse' => $warehouse->toArray()];
        });

        $handlers->register(
            'commerce.warehouse.list',
            fn (array $input, AuthContext $context) => [
                'warehouses' => array_map(
                    fn (WarehouseData $warehouse) => $warehouse->toArray(),
                    $this->app->make(ListWarehousesAction::class)->execute(
                        $context->tenantId,
                        isset($input['is_active']) ? (bool) $input['is_active'] : null,
                    ),
                ),
            ],
        );

        $handlers->register(
            'commerce.warehouse.stock',
            fn (array $input, AuthContext $context) => $this->app->make(GetWarehouseStockAction::class)->execute(
                tenantId: $context->tenantId,
                warehouseId: (int) $input['warehouse_id'],
                productId: (int) $input['product_id'],
                variantId: isset($input['variant_id']) ? (int) $input['variant_id'] : null,
            ),
        );

        $handlers->register('commerce.warehouse.nearest', function (array $input, AuthContext $context) {
            /** @var ?WarehouseData $warehouse */
            $warehouse = $this->app->make(FindNearestWarehouseAction::class)->execute(
                tenantId: $context->tenantId,
                productId: (int) $input['product_id'],
                customerLatitude: (float) $input['customer_latitude'],
                customerLongitude: (float) $input['customer_longitude'],
                requiredQuantity: (int) $input['required_quantity'],
                variantId: isset($input['variant_id']) ? (int) $input['variant_id'] : null,
            );

            return ['warehouse' => $warehouse?->toArray()];
        });

        $handlers->register('commerce.transfer.request', function (array $input, AuthContext $context) {
            /** @var WarehouseTransferData $transfer */
            $transfer = $this->app->make(RequestWarehouseTransferAction::class)->execute(
                tenantId: $context->tenantId,
                sourceWarehouseId: (int) $input['source_warehouse_id'],
                destinationWarehouseId: (int) $input['destination_warehouse_id'],
                requestedBy: $context->agentId,
                items: $input['items'],
                notes: $input['notes'] ?? null,
            );

            return ['transfer' => $transfer->toArray()];
        });

        $handlers->register('commerce.transfer.approve', function (array $input, AuthContext $context) {
            /** @var WarehouseTransferData $transfer */
            $transfer = $this->app->make(ApproveWarehouseTransferAction::class)->execute(
                id: (int) $input['transfer_id'],
                tenantId: $context->tenantId,
                approvedBy: $context->agentId,
            );

            return ['transfer' => $transfer->toArray()];
        });

        $handlers->register('commerce.transfer.complete', function (array $input, AuthContext $context) {
            /** @var WarehouseTransferData $transfer */
            $transfer = $this->app->make(CompleteWarehouseTransferAction::class)->execute(
                id: (int) $input['transfer_id'],
                tenantId: $context->tenantId,
            );

            return ['transfer' => $transfer->toArray()];
        });

        // Phase 5, Stage 3 (Bulk Operations, §7.23). See
        // CommerceCapabilities' own docblock for the 8-for-8 capability
        // renames this stage needed (the recurring 3-dot-segment gotcha).

        $handlers->register('commerce.bulk.import_products', function (array $input, AuthContext $context) {
            /** @var BulkOperationData $operation */
            $operation = $this->app->make(ImportProductsAction::class)->execute(
                tenantId: $context->tenantId,
                createdBy: $context->agentId,
                filePath: $input['file_path'],
                options: $input['options'] ?? [],
            );

            return ['operation' => $operation->toArray()];
        });

        $handlers->register('commerce.bulk.import_customers', function (array $input, AuthContext $context) {
            /** @var BulkOperationData $operation */
            $operation = $this->app->make(ImportCustomersAction::class)->execute(
                tenantId: $context->tenantId,
                createdBy: $context->agentId,
                filePath: $input['file_path'],
            );

            return ['operation' => $operation->toArray()];
        });

        $handlers->register('commerce.bulk.export_orders', function (array $input, AuthContext $context) {
            /** @var array{operation: BulkOperationData, downloadUrl: ?string} $result */
            $result = $this->app->make(ExportOrdersAction::class)->execute(
                tenantId: $context->tenantId,
                createdBy: $context->agentId,
                startDate: $input['start_date'] ?? null,
                endDate: $input['end_date'] ?? null,
                status: $input['status'] ?? null,
            );

            return ['operation' => $result['operation']->toArray(), 'download_url' => $result['downloadUrl']];
        });

        $handlers->register('commerce.bulk.update_price', function (array $input, AuthContext $context) {
            /** @var BulkOperationData $operation */
            $operation = $this->app->make(BulkPriceUpdateAction::class)->execute(
                tenantId: $context->tenantId,
                createdBy: $context->agentId,
                productIds: array_map(intval(...), $input['product_ids']),
                newPriceAmount: (int) $input['new_price'],
                newPriceCurrency: $input['currency'],
            );

            return ['operation' => $operation->toArray()];
        });

        $handlers->register('commerce.bulk.update_status', function (array $input, AuthContext $context) {
            /** @var BulkOperationData $operation */
            $operation = $this->app->make(BulkStatusUpdateAction::class)->execute(
                tenantId: $context->tenantId,
                createdBy: $context->agentId,
                productIds: array_map(intval(...), $input['product_ids']),
                newStatus: $input['new_status'],
            );

            return ['operation' => $operation->toArray()];
        });

        $handlers->register('commerce.bulk.update_inventory', function (array $input, AuthContext $context) {
            /** @var BulkOperationData $operation */
            $operation = $this->app->make(BulkInventoryUpdateAction::class)->execute(
                tenantId: $context->tenantId,
                createdBy: $context->agentId,
                updates: $input['updates'],
            );

            return ['operation' => $operation->toArray()];
        });

        $handlers->register('commerce.bulk.get', function (array $input, AuthContext $context) {
            /** @var BulkOperationData $operation */
            $operation = $this->app->make(GetBulkOperationAction::class)->execute((int) $input['operation_id'], $context->tenantId);

            return ['operation' => $operation->toArray()];
        });

        $handlers->register(
            'commerce.bulk.list',
            fn (array $input, AuthContext $context) => [
                'operations' => array_map(
                    fn (BulkOperationData $operation) => $operation->toArray(),
                    $this->app->make(ListBulkOperationsAction::class)->execute(
                        $context->tenantId,
                        $input['type'] ?? null,
                        $input['status'] ?? null,
                    ),
                ),
            ],
        );

        // Phase 5, Stage 4 (Advanced Discount Rules, §7.24). See
        // CommerceCapabilities' own docblock for the 5-of-7 capability
        // renames this stage needed (the recurring 3-dot-segment gotcha).

        $handlers->register('commerce.rule.create', function (array $input, AuthContext $context) {
            /** @var DiscountRuleData $rule */
            $rule = $this->app->make(CreateDiscountRuleAction::class)->execute(
                tenantId: $context->tenantId,
                name: $input['name'],
                discountType: $input['discount_type'],
                discountValue: (int) $input['discount_value'],
                priority: (int) $input['priority'],
                stackability: $input['stackability'],
                conditions: $input['conditions'] ?? [],
                description: $input['description'] ?? null,
                startsAt: $input['starts_at'] ?? null,
                expiresAt: $input['expires_at'] ?? null,
                maxUses: isset($input['max_uses']) ? (int) $input['max_uses'] : null,
            );

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('commerce.rule.update', function (array $input, AuthContext $context) {
            /** @var DiscountRuleData $rule */
            $rule = $this->app->make(UpdateDiscountRuleAction::class)->execute(
                id: (int) $input['rule_id'],
                tenantId: $context->tenantId,
                name: $input['name'],
                discountValue: (int) $input['discount_value'],
                priority: (int) $input['priority'],
                stackability: $input['stackability'],
                description: $input['description'] ?? null,
                startsAt: $input['starts_at'] ?? null,
                expiresAt: $input['expires_at'] ?? null,
                isActive: (bool) ($input['is_active'] ?? true),
            );

            return ['rule' => $rule->toArray()];
        });

        $handlers->register('commerce.rule.delete', function (array $input, AuthContext $context) {
            $this->app->make(DeleteDiscountRuleAction::class)->execute((int) $input['rule_id'], $context->tenantId);

            return ['success' => true];
        });

        $handlers->register('commerce.rule.get', function (array $input, AuthContext $context) {
            /** @var DiscountRuleData $rule */
            $rule = $this->app->make(GetDiscountRuleAction::class)->execute((int) $input['rule_id'], $context->tenantId);

            return ['rule' => $rule->toArray()];
        });

        $handlers->register(
            'commerce.rule.list',
            fn (array $input, AuthContext $context) => [
                'rules' => array_map(
                    fn (DiscountRuleData $rule) => $rule->toArray(),
                    $this->app->make(ListDiscountRulesAction::class)->execute(
                        $context->tenantId,
                        isset($input['is_active']) ? (bool) $input['is_active'] : null,
                    ),
                ),
            ],
        );

        $handlers->register('commerce.discount.apply', function (array $input, AuthContext $context) {
            $result = $this->app->make(ApplyDiscountsToCartAction::class)->execute(
                tenantId: $context->tenantId,
                agentId: $context->agentId,
                cartId: (int) $input['cart_id'],
            );

            return [
                'applied_discounts' => array_map(fn ($discount) => $discount->toArray(), $result['appliedDiscounts']),
                'total_discount' => ['amount' => $result['totalDiscountAmount'], 'currency' => $result['totalDiscountCurrency']],
            ];
        });

        $handlers->register(
            'commerce.discount.available',
            fn (array $input, AuthContext $context) => [
                'available_rules' => array_map(
                    fn (DiscountRuleData $rule) => $rule->toArray(),
                    $this->app->make(GetAvailableDiscountsAction::class)->execute(
                        $context->tenantId,
                        $context->agentId,
                        (int) $input['cart_id'],
                    ),
                ),
            ],
        );
    }
}
