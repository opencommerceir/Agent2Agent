<?php

namespace App\Modules\Reporting\Domain\Services;

/**
 * Pure, framework-free. `TopProductsQueryBuilder` already does the
 * expensive part in SQL — JOIN order_items to orders, GROUP BY
 * product_id, ORDER BY quantity_sold DESC, LIMIT — so this Generator's
 * only job is merging in each product's display name (resolved by the
 * calling Action via Commerce's own `ProductRepositoryInterface`, the
 * same cross-module Dependency Inversion CreateInvoiceAction's own
 * per-item product-name lookup already established). Looping over an
 * already-limited (≤ `limit`, default 10) array here is not the kind of
 * "loop instead of an aggregate" this module's own rule 5 warns against
 * — that rule is about not summing/counting raw transactional rows in
 * PHP, not about assembling a handful of already-aggregated rows into
 * their final shape.
 */
final class TopProductsReportGenerator
{
    /**
     * @param list<array{product_id: int, quantity_sold: int, total_revenue: int}> $rows
     * @param array<int, string> $productNames product_id => name
     * @return list<array{product_id: int, name: string, quantity_sold: int, total_revenue: int}>
     */
    public function generate(array $rows, array $productNames): array
    {
        return array_map(
            fn (array $row) => [
                'product_id' => $row['product_id'],
                'name' => $productNames[$row['product_id']] ?? "Product #{$row['product_id']}",
                'quantity_sold' => $row['quantity_sold'],
                'total_revenue' => $row['total_revenue'],
            ],
            $rows,
        );
    }
}
