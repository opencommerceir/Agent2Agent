<?php

namespace App\Modules\Shipping\Interfaces\MCP;

/**
 * The capability manifest for the Shipping module — what
 * ShippingCapabilitiesSeeder registers into the Capability Registry and
 * ShippingServiceProvider wires into CapabilityHandlerRegistry. Kept as
 * plain data here, separate from the seeder's idempotency plumbing, the
 * same split every prior module's own capability manifest established.
 *
 * 6 of the 8 Stage 1 requested names were already exactly 3 dot-separated
 * segments; `shipping.shipment.status.update` and
 * `shipping.tracking.event.add` were both 4 — CapabilityName requires
 * exactly 3 (HANDOFF gotcha #2, hit again here the same way
 * WooCommerce's/CRM's/Workflows' capabilities hit it). Renamed to
 * `shipping.shipment.transition` and `shipping.tracking.add`,
 * restructuring the same 3 semantic groupings the request specified
 * rather than inventing new, more granular ones.
 *
 * Stage 2 (Shipping Provider Connector) added the last 3:
 * `shipping.provider.rates`/`.fulfill`, `shipping.tracking.sync` — the
 * requested `shipping.provider.shipment.create` hit gotcha #2 again (4
 * segments), renamed to `shipping.provider.fulfill`.
 */
final class ShippingCapabilities
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
                'name' => 'shipping.method.create',
                'description' => 'Define a ShippingMethod: a base rate, a per-kg rate, and an estimated delivery window',
                'inputSchema' => [
                    'name' => 'string',
                    'base_rate' => 'integer',
                    'rate_per_kg' => 'integer',
                    'estimated_days_min' => 'integer',
                    'estimated_days_max' => 'integer',
                ],
                'outputSchema' => ['method' => 'array'],
                'requiredPermissions' => ['shipping.methods.create'],
            ],
            [
                'name' => 'shipping.method.list',
                'description' => "List the tenant's ShippingMethods, optionally filtered by is_active",
                // is_active is optional.
                'inputSchema' => [],
                'outputSchema' => ['methods' => 'array'],
                'requiredPermissions' => ['shipping.methods.read'],
            ],
            [
                'name' => 'shipping.rate.calculate',
                'description' => 'Preview the shipping cost for a given weight under a given ShippingMethod — no side effects',
                'inputSchema' => ['shipping_method_id' => 'integer', 'weight_grams' => 'integer'],
                'outputSchema' => ['rate' => 'array'],
                'requiredPermissions' => ['shipping.rates.read'],
            ],
            [
                'name' => 'shipping.shipment.create',
                'description' => 'Fulfill an Order with a real Shipment: weighs its Products, prices it, generates a tracking number, and records the assignment on the Order',
                'inputSchema' => ['order_id' => 'integer', 'shipping_method_id' => 'integer'],
                'outputSchema' => ['shipment' => 'array'],
                'requiredPermissions' => ['shipping.shipments.create'],
            ],
            [
                'name' => 'shipping.shipment.get',
                'description' => 'Get a Shipment by id',
                'inputSchema' => ['shipment_id' => 'integer'],
                'outputSchema' => ['shipment' => 'array'],
                'requiredPermissions' => ['shipping.shipments.read'],
            ],
            [
                'name' => 'shipping.shipment.list',
                'description' => "List the tenant's Shipments, optionally filtered by status or order_id",
                // status and order_id are both optional.
                'inputSchema' => [],
                'outputSchema' => ['shipments' => 'array'],
                'requiredPermissions' => ['shipping.shipments.read'],
            ],
            [
                'name' => 'shipping.shipment.transition',
                'description' => "Transition a Shipment's authoritative status (pending -> in_transit -> delivered, or returned/exception)",
                'inputSchema' => ['shipment_id' => 'integer', 'status' => 'string'],
                'outputSchema' => ['shipment' => 'array'],
                'requiredPermissions' => ['shipping.shipments.update'],
            ],
            [
                'name' => 'shipping.tracking.add',
                'description' => "Append one entry to a Shipment's tracking history — does not itself change the Shipment's own status",
                // location is optional.
                'inputSchema' => ['shipment_id' => 'integer', 'status' => 'string', 'description' => 'string'],
                'outputSchema' => ['event' => 'array'],
                'requiredPermissions' => ['shipping.shipments.update'],
            ],
            [
                'name' => 'shipping.provider.rates',
                'description' => 'Get live rates for a weight/destination from an external shipping provider (or the Mock stand-in)',
                // provider is optional — defaults to config('shipping.provider').
                'inputSchema' => ['weight_grams' => 'integer', 'destination' => 'object'],
                'outputSchema' => ['rates' => 'array'],
                'requiredPermissions' => ['shipping.providers.read'],
            ],
            [
                'name' => 'shipping.provider.fulfill',
                'description' => 'Hand an already-created Shipment to an external shipping provider, recording its own tracking number',
                // provider is optional. Renamed from the requested
                // shipping.provider.shipment.create — 4 dot-separated
                // segments, CapabilityName requires exactly 3 (same
                // treatment shipping.shipment.status.update got in Stage 1).
                'inputSchema' => ['shipment_id' => 'integer'],
                'outputSchema' => ['provider_shipment' => 'array'],
                'requiredPermissions' => ['shipping.providers.create'],
            ],
            [
                'name' => 'shipping.tracking.sync',
                'description' => "Pull tracking updates from an external shipping provider and fold in whatever is genuinely new — updates the Shipment's own status if the newest event is a legal transition",
                // provider is optional.
                'inputSchema' => ['tracking_number' => 'string'],
                'outputSchema' => ['events' => 'array', 'synced_count' => 'integer'],
                'requiredPermissions' => ['shipping.providers.sync'],
            ],
        ];
    }
}
