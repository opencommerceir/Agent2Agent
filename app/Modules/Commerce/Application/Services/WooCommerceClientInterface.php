<?php

namespace App\Modules\Commerce\Application\Services;

/**
 * Outbound port to the WooCommerce REST API (`/wp-json/wc/v3/products`).
 * Returns raw decoded JSON — never a UCPProduct or any Domain type — so
 * the translation boundary stays entirely inside WooCommerceProductMapper
 * (Connector Conventions: communication and translation are separate
 * concerns). WooCommerceClient is the real Guzzle-backed implementation;
 * MockWooCommerceHttpClient (Infrastructure/Http) is the only other one,
 * used until a real store's credentials exist to test against honestly
 * (same reasoning HANDOFF gives for every Connector).
 */
interface WooCommerceClientInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function getProducts(int $page = 1, int $perPage = 20): array;

    /**
     * @return array<string, mixed>|null
     */
    public function getProduct(string $externalId): ?array;
}
