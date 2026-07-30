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
        ];
    }
}
