<?php

namespace App\Modules\Commerce\Interfaces\MCP;

/**
 * The capability manifest for the Commerce module — what
 * CommerceCapabilitiesSeeder registers into the Capability Registry and
 * CommerceServiceProvider wires into CapabilityHandlerRegistry. Kept as
 * plain data here, separate from the seeder's idempotency plumbing, so
 * "what capabilities does Commerce expose" is readable on its own (same
 * split DemoCapabilities established).
 */
final class CommerceCapabilities
{
    /**
     * @return list<array{
     *     name: string,
     *     description: string,
     *     inputSchema: array<string, string>,
     *     outputSchema: array<string, string>,
     *     requiredPermissions: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'commerce.product.search',
                'description' => 'Search for products by query',
                'inputSchema' => ['query' => 'string', 'limit' => 'integer'],
                'outputSchema' => ['products' => 'array'],
                'requiredPermissions' => ['commerce.products.read'],
            ],
            [
                'name' => 'commerce.cart.add',
                'description' => 'Add a product to the calling Agent\'s cart',
                'inputSchema' => ['product_id' => 'integer', 'quantity' => 'integer'],
                'outputSchema' => ['cart' => 'array', 'message' => 'string'],
                'requiredPermissions' => ['commerce.cart.manage'],
            ],
            [
                'name' => 'commerce.cart.get',
                'description' => "Get the calling Agent's current cart",
                'inputSchema' => [],
                'outputSchema' => ['cart' => 'array'],
                'requiredPermissions' => ['commerce.cart.read'],
            ],
            [
                'name' => 'commerce.order.place',
                'description' => "Place an Order from the calling Agent's own cart",
                // notes is optional — deliberately left out of inputSchema
                // (MCPRequestValidationService treats every declared field
                // as required; there is no "optional but typed" yet).
                'inputSchema' => ['cart_id' => 'integer'],
                'outputSchema' => ['order' => 'array'],
                'requiredPermissions' => ['commerce.orders.create'],
            ],
            [
                'name' => 'commerce.order.get',
                'description' => 'Get an Order by id',
                'inputSchema' => ['order_id' => 'integer'],
                'outputSchema' => ['order' => 'array'],
                'requiredPermissions' => ['commerce.orders.read'],
            ],
            [
                'name' => 'commerce.order.list',
                'description' => "List the tenant's Orders, optionally filtered by status",
                // status and limit are both optional — same reasoning as
                // commerce.order.place's notes field.
                'inputSchema' => [],
                'outputSchema' => ['orders' => 'array'],
                'requiredPermissions' => ['commerce.orders.read'],
            ],
            [
                'name' => 'commerce.customer.create',
                'description' => 'Register a new Customer',
                // phone and address are optional — same reasoning as
                // commerce.order.place's notes field.
                'inputSchema' => ['first_name' => 'string', 'last_name' => 'string', 'email' => 'string'],
                'outputSchema' => ['customer' => 'array'],
                'requiredPermissions' => ['commerce.customers.create'],
            ],
            [
                'name' => 'commerce.customer.get',
                'description' => 'Get a Customer by id',
                'inputSchema' => ['customer_id' => 'integer'],
                'outputSchema' => ['customer' => 'array'],
                'requiredPermissions' => ['commerce.customers.read'],
            ],
            [
                'name' => 'commerce.customer.list',
                'description' => "List the tenant's Customers, optionally filtered by status",
                'inputSchema' => [],
                'outputSchema' => ['customers' => 'array'],
                'requiredPermissions' => ['commerce.customers.read'],
            ],
            [
                'name' => 'commerce.checkout.calculate',
                'description' => 'Preview the pricing for a cart, optionally with a coupon, without charging anything',
                // coupon_code is optional — same reasoning as
                // commerce.order.place's notes field.
                'inputSchema' => ['cart_id' => 'integer'],
                'outputSchema' => ['pricing' => 'array'],
                'requiredPermissions' => ['commerce.checkout.read'],
            ],
            [
                'name' => 'commerce.checkout.process',
                'description' => 'Charge payment for a cart and place the resulting Order',
                // coupon_code is optional; payment_details is an
                // arbitrary object whose shape depends on payment_method,
                // so it is intentionally left untyped/unvalidated here.
                'inputSchema' => ['cart_id' => 'integer', 'payment_method' => 'string'],
                'outputSchema' => ['order' => 'array', 'payment' => 'array'],
                'requiredPermissions' => ['commerce.checkout.create'],
            ],
            [
                'name' => 'commerce.payment.refund',
                'description' => 'Refund a completed Payment, restoring its Order\'s Inventory',
                'inputSchema' => ['payment_id' => 'integer'],
                'outputSchema' => ['payment' => 'array', 'message' => 'string'],
                'requiredPermissions' => ['commerce.payments.refund'],
            ],
            [
                'name' => 'commerce.coupon.create',
                'description' => 'Create a new discount Coupon',
                // expires_at and max_uses are optional.
                'inputSchema' => ['code' => 'string', 'discount_type' => 'string', 'discount_value' => 'integer'],
                'outputSchema' => ['coupon' => 'array'],
                'requiredPermissions' => ['commerce.coupons.create'],
            ],
            [
                'name' => 'commerce.woocommerce.sync',
                'description' => 'Sync products from the connected WooCommerce store into the catalog',
                // page and limit are both optional — same reasoning as
                // commerce.order.place's notes field.
                'inputSchema' => [],
                'outputSchema' => ['result' => 'array'],
                'requiredPermissions' => ['commerce.connectors.sync'],
            ],
            [
                'name' => 'commerce.woocommerce.get',
                'description' => 'Fetch a single product directly from the connected WooCommerce store by its external id',
                'inputSchema' => ['external_id' => 'string'],
                'outputSchema' => ['product' => 'array'],
                'requiredPermissions' => ['commerce.connectors.read'],
            ],
        ];
    }
}
