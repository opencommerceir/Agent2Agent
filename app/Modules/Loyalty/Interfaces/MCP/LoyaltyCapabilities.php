<?php

namespace App\Modules\Loyalty\Interfaces\MCP;

/**
 * The capability manifest for the Loyalty module — what
 * LoyaltyCapabilitiesSeeder registers into the Capability Registry and
 * LoyaltyServiceProvider wires into CapabilityHandlerRegistry. Kept as
 * plain data here, separate from the seeder's idempotency plumbing, the
 * same split Commerce's/CRM's/Finance's/Workflows' own capability
 * manifests established.
 *
 * All 8 requested names are already exactly 3 dot-separated segments —
 * unlike WooCommerce/CRM/Workflows (HANDOFF gotcha #2), no rename was
 * needed this stage. Same for the 7 requested permission groupings.
 *
 * `ExpirePointsAction` is the one built, tested Action not wired here —
 * no `loyalty.points.expire` capability was among the 8 requested (see
 * that Action's own docblock for why, and why that's fine — HANDOFF §6).
 */
final class LoyaltyCapabilities
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
                'name' => 'loyalty.account.get',
                'description' => "Get a Customer's LoyaltyAccount",
                'inputSchema' => ['customer_id' => 'integer'],
                'outputSchema' => ['account' => 'array'],
                'requiredPermissions' => ['loyalty.accounts.read'],
            ],
            [
                'name' => 'loyalty.account.create',
                'description' => 'Open a LoyaltyAccount for a Customer',
                'inputSchema' => ['customer_id' => 'integer'],
                'outputSchema' => ['account' => 'array'],
                'requiredPermissions' => ['loyalty.accounts.create'],
            ],
            [
                'name' => 'loyalty.points.earn',
                'description' => "Credit points to a Customer's LoyaltyAccount",
                // reference_id is optional.
                'inputSchema' => ['customer_id' => 'integer', 'points' => 'integer', 'description' => 'string'],
                'outputSchema' => ['transaction' => 'array', 'new_balance' => 'integer'],
                'requiredPermissions' => ['loyalty.points.manage'],
            ],
            [
                'name' => 'loyalty.points.redeem',
                'description' => 'Spend points on a Reward',
                'inputSchema' => ['customer_id' => 'integer', 'points' => 'integer', 'reward_id' => 'integer'],
                'outputSchema' => ['redemption' => 'array', 'new_balance' => 'integer'],
                'requiredPermissions' => ['loyalty.points.redeem'],
            ],
            [
                'name' => 'loyalty.reward.create',
                'description' => 'Define a Reward Customers can spend points on',
                // discount_amount is optional (required only for discount_coupon).
                'inputSchema' => ['name' => 'string', 'reward_type' => 'string', 'points_required' => 'integer'],
                'outputSchema' => ['reward' => 'array'],
                'requiredPermissions' => ['loyalty.rewards.manage'],
            ],
            [
                'name' => 'loyalty.reward.get',
                'description' => 'Get a Reward by id',
                'inputSchema' => ['reward_id' => 'integer'],
                'outputSchema' => ['reward' => 'array'],
                'requiredPermissions' => ['loyalty.rewards.read'],
            ],
            [
                'name' => 'loyalty.reward.list',
                'description' => "List the tenant's Rewards, optionally filtered by is_active",
                // is_active is optional.
                'inputSchema' => [],
                'outputSchema' => ['rewards' => 'array'],
                'requiredPermissions' => ['loyalty.rewards.read'],
            ],
            [
                'name' => 'loyalty.transaction.list',
                'description' => "List a Customer's PointTransaction history",
                // limit is optional.
                'inputSchema' => ['customer_id' => 'integer'],
                'outputSchema' => ['transactions' => 'array'],
                'requiredPermissions' => ['loyalty.transactions.read'],
            ],
        ];
    }
}
