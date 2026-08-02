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
                // variant_id is optional (Phase 5, Stage 1 — Product
                // Variants, §7.21) — omitted, this adds the parent
                // Product itself, exactly as before this stage.
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
                // commerce.order.place's notes field. region is optional
                // too (Phase 3.2, Finance module) — a TaxRegion string
                // (e.g. "US-CA") to look up a real configured TaxRate
                // through TaxRateProviderInterface; omitted, it falls
                // back to the tenant's TaxRegion::default() row, and
                // failing that, Commerce's own hardcoded 9%.
                'inputSchema' => ['cart_id' => 'integer'],
                'outputSchema' => ['pricing' => 'array'],
                'requiredPermissions' => ['commerce.checkout.read'],
            ],
            [
                'name' => 'commerce.checkout.process',
                'description' => 'Charge payment for a cart and place the resulting Order',
                // coupon_code and region are optional (region: same
                // reasoning as commerce.checkout.calculate's); payment_details
                // is an arbitrary object whose shape depends on
                // payment_method, so it is intentionally left
                // untyped/unvalidated here.
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
            // Phase 5, Stage 1 (Product Variants, §7.21). The request's
            // own commerce.variant.attribute.create/.list and
            // commerce.variant.combinations.generate were all 4
            // dot-separated segments — CapabilityName requires exactly 3
            // (HANDOFF gotcha #2) — renamed below to
            // commerce.attribute.create/.list and commerce.variant.generate.
            [
                'name' => 'commerce.attribute.create',
                'description' => 'Create a tenant-scoped variant attribute (e.g. "Color") together with all of its values',
                'inputSchema' => ['name' => 'string', 'values' => 'array'],
                'outputSchema' => ['attribute' => 'array'],
                'requiredPermissions' => ['commerce.attributes.manage'],
            ],
            [
                'name' => 'commerce.attribute.list',
                'description' => "List the tenant's own variant attributes",
                'inputSchema' => [],
                'outputSchema' => ['attributes' => 'array'],
                'requiredPermissions' => ['commerce.attributes.read'],
            ],
            [
                'name' => 'commerce.variant.create',
                'description' => 'Create one ProductVariant for a Product from an explicit attribute combination',
                // image_url and initial_stock are optional — same
                // reasoning as commerce.order.place's notes field.
                'inputSchema' => ['product_id' => 'integer', 'attributes' => 'array', 'price_amount' => 'integer', 'price_currency' => 'string'],
                'outputSchema' => ['variant' => 'array'],
                'requiredPermissions' => ['commerce.variants.manage'],
            ],
            [
                'name' => 'commerce.variant.update',
                'description' => "Update a ProductVariant's price, image, active status, and optionally its stock",
                // image_url, is_active and stock_quantity are all optional.
                'inputSchema' => ['variant_id' => 'integer', 'price_amount' => 'integer', 'price_currency' => 'string'],
                'outputSchema' => ['variant' => 'array'],
                'requiredPermissions' => ['commerce.variants.manage'],
            ],
            [
                'name' => 'commerce.variant.delete',
                'description' => 'Soft-delete a ProductVariant',
                'inputSchema' => ['variant_id' => 'integer'],
                'outputSchema' => ['message' => 'string'],
                'requiredPermissions' => ['commerce.variants.manage'],
            ],
            [
                'name' => 'commerce.variant.get',
                'description' => 'Get a ProductVariant by id, including its current stock',
                'inputSchema' => ['variant_id' => 'integer'],
                'outputSchema' => ['variant' => 'array'],
                'requiredPermissions' => ['commerce.variants.read'],
            ],
            [
                'name' => 'commerce.variant.list',
                'description' => "List a Product's own ProductVariants",
                'inputSchema' => ['product_id' => 'integer'],
                'outputSchema' => ['variants' => 'array'],
                'requiredPermissions' => ['commerce.variants.read'],
            ],
            [
                'name' => 'commerce.variant.generate',
                'description' => 'Generate every ProductVariant combination across a set of variant attributes for a Product',
                'inputSchema' => ['product_id' => 'integer', 'attribute_ids' => 'array', 'price_amount' => 'integer', 'price_currency' => 'string'],
                'outputSchema' => ['variants' => 'array', 'count' => 'integer'],
                'requiredPermissions' => ['commerce.variants.manage'],
            ],
        ];
    }
}
