<?php

namespace App\Modules\Commerce\Domain\ValueObjects;

/**
 * The raw shape a WooCommerce REST API product payload arrives in
 * (`GET /wp-json/wc/v3/products`), snake_case fields normalized to
 * camelCase properties here — never handed to anything outside the
 * Connector boundary. WooCommerceProductMapper is the only consumer;
 * everything past it only ever sees UCPProduct (UCP Compliance).
 */
final class WooCommerceProductData
{
    /**
     * @param list<array{id: int, name: string, slug?: string}> $categories
     * @param list<array{id: int, src: string}> $images
     */
    public function __construct(
        public readonly WooCommerceProductId $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $type,
        public readonly string $status,
        public readonly string $price,
        public readonly string $regularPrice,
        public readonly ?string $description,
        public readonly ?string $shortDescription,
        public readonly string $sku,
        public readonly ?int $stockQuantity,
        public readonly bool $manageStock,
        public readonly array $categories = [],
        public readonly array $images = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: new WooCommerceProductId((int) ($data['id'] ?? 0)),
            name: (string) ($data['name'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            type: (string) ($data['type'] ?? 'simple'),
            status: (string) ($data['status'] ?? 'draft'),
            price: (string) ($data['price'] ?? ($data['regular_price'] ?? '0')),
            regularPrice: (string) ($data['regular_price'] ?? '0'),
            description: $data['description'] ?? null,
            shortDescription: $data['short_description'] ?? null,
            sku: (string) ($data['sku'] ?? ''),
            stockQuantity: isset($data['stock_quantity']) ? (int) $data['stock_quantity'] : null,
            manageStock: (bool) ($data['manage_stock'] ?? false),
            categories: $data['categories'] ?? [],
            images: $data['images'] ?? [],
        );
    }
}
