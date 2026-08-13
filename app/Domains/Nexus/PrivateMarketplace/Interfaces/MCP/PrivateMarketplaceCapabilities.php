<?php

namespace App\Domains\Nexus\PrivateMarketplace\Interfaces\MCP;

/**
 * Same manifest/seeder split MarketplaceCapabilities established. Both
 * capabilities require real membership (checked inside the Action/Query
 * itself via ResolveActingBusinessAction + the member-gated query), not
 * just a generic permission — the permission below only proves "this Agent
 * may use Private Marketplace capabilities at all," same shape
 * nexus.marketplace.read does for public search.
 */
final class PrivateMarketplaceCapabilities
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
                'name' => 'nexus.private_marketplace.search',
                'description' => 'Search a Private Marketplace the calling Business is a member of — empty for non-members',
                'inputSchema' => ['marketplace_id' => 'integer'],
                'outputSchema' => ['listings' => 'array'],
                'requiredPermissions' => ['nexus.marketplace.read'],
            ],
            [
                'name' => 'nexus.private_marketplace.list_listing',
                'description' => 'Post a confidentially-priced listing into a Private Marketplace the calling Business is a member of',
                'inputSchema' => [
                    'marketplace_id' => 'integer',
                    'catalog_item_type' => 'string',
                    'catalog_item_id' => 'integer',
                    'special_price_amount' => 'integer',
                    'special_price_currency' => 'string',
                ],
                'outputSchema' => ['listing' => 'object'],
                'requiredPermissions' => ['nexus.marketplace.read'],
            ],
        ];
    }
}
