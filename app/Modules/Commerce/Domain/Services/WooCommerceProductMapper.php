<?php

namespace App\Modules\Commerce\Domain\Services;

use App\Modules\Commerce\Domain\UCP\UCPProduct;
use App\Modules\Commerce\Domain\ValueObjects\WooCommerceProductData;

/**
 * The single place that knows how a raw WooCommerce product payload
 * translates into the platform's normalized UCPProduct shape (UCP
 * Compliance — external structures never leak past a Connector). Pure and
 * framework-free like PricingService/CouponValidationService: no
 * Eloquent, no facades, easy to unit test in isolation from HTTP or the
 * database.
 */
final class WooCommerceProductMapper
{
    public function toUCP(WooCommerceProductData $data, string $currency = 'USD'): UCPProduct
    {
        $category = $data->categories[0] ?? null;
        $image = $data->images[0] ?? null;

        $priceDecimal = $data->price !== '' ? $data->price : $data->regularPrice;
        $sku = $data->sku !== '' ? $data->sku : ('WOO-'.$data->id->value());

        return new UCPProduct(
            externalId: (string) $data->id->value(),
            sourceSystem: 'woocommerce',
            sku: $sku,
            name: $data->name,
            description: $data->description,
            priceAmount: self::toCents($priceDecimal),
            priceCurrency: $currency,
            categoryIds: array_map(
                static fn (array $category) => (string) $category['id'],
                $data->categories,
            ),
            isAvailable: $data->status === 'publish',
            attributes: [
                'source_system' => 'woocommerce',
                'external_id' => $data->id->value(),
                'type' => $data->type,
                'stock_quantity' => $data->stockQuantity,
                'manage_stock' => $data->manageStock,
                'category_name' => $category['name'] ?? null,
                'image_url' => $image['src'] ?? null,
            ],
        );
    }

    /**
     * WooCommerce sends price as a decimal string (e.g. "29.99"), never a
     * number — converted here to integer cents (Money as Integer,
     * CLAUDE.md) the moment it enters the platform, so no float-typed
     * money value ever exists past this boundary.
     */
    private static function toCents(string $decimal): int
    {
        if (trim($decimal) === '') {
            return 0;
        }

        return (int) round(((float) $decimal) * 100);
    }
}
