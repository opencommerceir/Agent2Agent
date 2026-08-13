<?php

namespace App\Domains\Nexus\Automation\Interfaces\MCP;

/**
 * Capability manifest for the Automation domain (Phase 8/M4) — same
 * manifest -> Seeder -> CapabilityHandlerRegistry split every earlier
 * domain's own MCP surface already established. An Agent can manage its own
 * Business's workflow rules directly, matching CLAUDE.md's "Agent2Agent"
 * framing — automation is exactly the kind of thing an autonomous Agent,
 * not just a human in the portal, should be able to configure.
 *
 * Names are flat `nexus.automation.<verb>` (not `nexus.automation.rule.*`)
 * — Core's own `CapabilityName` enforces exactly three dot-separated
 * segments (`domain.resource.action`), so "rule" cannot be a fourth
 * segment; `automation` is already the resource, the verb is the action.
 */
final class AutomationCapabilities
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
                'name' => 'nexus.automation.create_recurring_order',
                'description' => 'Schedule a recurring order — auto-opens a fresh Negotiation with the same counterparty/item/price/quantity every N days',
                'inputSchema' => [
                    'counterparty_business_id' => 'integer',
                    'catalog_item_type' => 'string',
                    'catalog_item_id' => 'integer',
                    'price_amount' => 'integer',
                    'price_currency' => 'string',
                    'quantity' => 'integer',
                    'interval_days' => 'integer',
                ],
                'outputSchema' => ['rule' => 'array'],
                'requiredPermissions' => ['nexus.automation.manage'],
            ],
            [
                'name' => 'nexus.automation.create_inventory_alert',
                'description' => 'Alert when a Product\'s own stock drops to or below a threshold, auto-searching the marketplace for restocking candidates',
                'inputSchema' => ['product_id' => 'integer', 'threshold_quantity' => 'integer'],
                'outputSchema' => ['rule' => 'array'],
                'requiredPermissions' => ['nexus.automation.manage'],
            ],
            [
                'name' => 'nexus.automation.create_price_alert',
                'description' => 'Alert when a catalog item\'s live listed price crosses a target threshold',
                'inputSchema' => [
                    'catalog_item_type' => 'string',
                    'catalog_item_id' => 'integer',
                    'target_price_amount' => 'integer',
                    'direction' => 'string',
                ],
                'outputSchema' => ['rule' => 'array'],
                'requiredPermissions' => ['nexus.automation.manage'],
            ],
            [
                'name' => 'nexus.automation.list',
                'description' => 'List the calling Business\'s own automation rules',
                // Free, like nexus.credit.balance — checking your own
                // configured rules must never be gated by the CostGate
                // (Phase 3/M2).
                'inputSchema' => [],
                'outputSchema' => ['rules' => 'array'],
                'requiredPermissions' => ['nexus.automation.read'],
            ],
            [
                'name' => 'nexus.automation.pause',
                'description' => 'Pause an Active automation rule the calling Business owns',
                'inputSchema' => ['rule_id' => 'integer'],
                'outputSchema' => ['rule' => 'array'],
                'requiredPermissions' => ['nexus.automation.manage'],
            ],
            [
                'name' => 'nexus.automation.resume',
                'description' => 'Resume a Paused automation rule the calling Business owns',
                'inputSchema' => ['rule_id' => 'integer'],
                'outputSchema' => ['rule' => 'array'],
                'requiredPermissions' => ['nexus.automation.manage'],
            ],
            [
                'name' => 'nexus.automation.delete',
                'description' => 'Delete an automation rule the calling Business owns',
                'inputSchema' => ['rule_id' => 'integer'],
                'outputSchema' => [],
                'requiredPermissions' => ['nexus.automation.manage'],
            ],
        ];
    }
}
