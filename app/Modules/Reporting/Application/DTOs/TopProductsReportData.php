<?php

namespace App\Modules\Reporting\Application\DTOs;

/**
 * Structured data transfer for a computed Top Products Report. Built via
 * `fromArray()` — see SalesReportData's own docblock for why.
 */
final class TopProductsReportData
{
    /**
     * @param list<array{productId: int, name: string, quantitySold: int, totalRevenue: int}> $products
     */
    public function __construct(
        public readonly array $products,
    ) {
    }

    /**
     * @param list<array{product_id: int, name: string, quantity_sold: int, total_revenue: int}> $rows
     */
    public static function fromArray(array $rows): self
    {
        return new self(
            products: array_map(
                fn (array $row) => [
                    'productId' => $row['product_id'],
                    'name' => $row['name'],
                    'quantitySold' => $row['quantity_sold'],
                    'totalRevenue' => $row['total_revenue'],
                ],
                $rows,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'products' => $this->products,
        ];
    }
}
