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
        ];
    }
}
