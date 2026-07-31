<?php

namespace App\Modules\Reporting\Domain\Services;

/**
 * Pure, framework-free — the same shape TopProductsReportGenerator has:
 * `TopCustomersQueryBuilder` already does the GROUP BY/ORDER BY/LIMIT in
 * SQL; this Generator only merges in each Customer's display name
 * (resolved by the calling Action via Commerce's own
 * `CustomerRepositoryInterface`).
 */
final class TopCustomersReportGenerator
{
    /**
     * @param list<array{customer_id: int, total_orders: int, total_spent: int}> $rows
     * @param array<int, string> $customerNames customer_id => full name
     * @return list<array{customer_id: int, name: string, total_orders: int, total_spent: int}>
     */
    public function generate(array $rows, array $customerNames): array
    {
        return array_map(
            fn (array $row) => [
                'customer_id' => $row['customer_id'],
                'name' => $customerNames[$row['customer_id']] ?? "Customer #{$row['customer_id']}",
                'total_orders' => $row['total_orders'],
                'total_spent' => $row['total_spent'],
            ],
            $rows,
        );
    }
}
