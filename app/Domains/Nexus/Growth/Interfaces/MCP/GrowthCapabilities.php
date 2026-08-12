<?php

namespace App\Domains\Nexus\Growth\Interfaces\MCP;

/**
 * Capability manifest for the Growth domain (Phase 5) — same
 * manifest -> Seeder -> CapabilityHandlerRegistry split every earlier
 * domain's own MCP surface already established (CreditCapabilities,
 * MarketplaceCapabilities). Grows across Phase 5's milestones (referral in
 * M1, invite in M2, coalition in M3) the same way NexusServiceProvider's own
 * registerMcpCapabilityHandlers() grew one private method per milestone in
 * Phase 2/3.
 */
final class GrowthCapabilities
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
                'name' => 'nexus.referral.status',
                'description' => "Check the calling Business's own referral code, referral counts, and reward status",
                // Free, like nexus.credit.balance — checking your own
                // referral standing must never itself be gated by the
                // CostGate (Phase 3/M2).
                'inputSchema' => [],
                'outputSchema' => [
                    'code' => 'string|null',
                    'tier1Count' => 'integer',
                    'tier1RewardedCount' => 'integer',
                    'tier2Count' => 'integer',
                    'referrals' => 'array',
                ],
                'requiredPermissions' => ['nexus.growth.read'],
            ],
            [
                'name' => 'nexus.invite.send',
                'description' => 'Send a Nexus onboarding invite (pre-filled with the calling Business\'s own referral code) to a named lead by email',
                // message_variant is optional (A/B testing, Phase 5/M5) —
                // same "declared fields are always required, so leave
                // optional ones out" rule NegotiationCapabilities' own
                // manifest already follows.
                'inputSchema' => [
                    'invitee_name' => 'string',
                    'invitee_email' => 'string',
                ],
                'outputSchema' => [
                    'id' => 'integer',
                    'inviteeName' => 'string',
                    'inviteeEmail' => 'string',
                    'status' => 'string',
                    'createdAt' => 'string',
                ],
                'requiredPermissions' => ['nexus.growth.manage'],
            ],
            [
                'name' => 'nexus.coalition.create',
                'description' => 'Organize a Group Buying coalition against a target supplier for one catalog item, requesting a bulk discount',
                'inputSchema' => [
                    'target_business_id' => 'integer',
                    'catalog_item_type' => 'string',
                    'catalog_item_id' => 'integer',
                    'unit_price_amount' => 'integer',
                    'unit_price_currency' => 'string',
                    'min_participants' => 'integer',
                    'discount_percent' => 'number',
                    'quantity' => 'integer',
                ],
                'outputSchema' => ['coalition' => 'array'],
                'requiredPermissions' => ['nexus.growth.manage'],
            ],
            [
                'name' => 'nexus.coalition.join',
                'description' => 'Join an open (Forming) coalition with a committed quantity',
                'inputSchema' => ['coalition_id' => 'integer', 'quantity' => 'integer'],
                'outputSchema' => ['coalition' => 'array'],
                'requiredPermissions' => ['nexus.growth.manage'],
            ],
            [
                'name' => 'nexus.coalition.list',
                'description' => 'List open (Forming) coalitions the calling Business can still join',
                'inputSchema' => [],
                'outputSchema' => ['coalitions' => 'array'],
                'requiredPermissions' => ['nexus.growth.read'],
            ],
            [
                'name' => 'nexus.coalition.close',
                'description' => 'Organizer-only: once minParticipants is reached, aggregate every member\'s quantity and open the bulk Negotiation with the target supplier',
                'inputSchema' => ['coalition_id' => 'integer'],
                'outputSchema' => ['coalition' => 'array'],
                'requiredPermissions' => ['nexus.growth.manage'],
            ],
            [
                'name' => 'nexus.coalition.leave',
                'description' => 'Leave a coalition the calling Business previously joined, while it is still Forming',
                'inputSchema' => ['coalition_id' => 'integer'],
                'outputSchema' => [],
                'requiredPermissions' => ['nexus.growth.manage'],
            ],
            [
                'name' => 'nexus.coalition.cancel',
                'description' => 'Organizer-only: cancel a coalition (Forming or Negotiating)',
                'inputSchema' => ['coalition_id' => 'integer'],
                'outputSchema' => ['coalition' => 'array'],
                'requiredPermissions' => ['nexus.growth.manage'],
            ],
        ];
    }
}
