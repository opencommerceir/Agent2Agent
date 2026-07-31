<?php

namespace App\Modules\Reporting\Application\DTOs;

/**
 * Structured data transfer for a computed Top Customers Report. Built
 * via `fromArray()` — see SalesReportData's own docblock for why.
 */
final class TopCustomersReportData
{
    /**
     * @param list<array{customerId: int, name: string, totalOrders: int, totalSpent: int}> $customers
     */
    public function __construct(
        public readonly array $customers,
    ) {
    }

    /**
     * @param list<array{customer_id: int, name: string, total_orders: int, total_spent: int}> $rows
     */
    public static function fromArray(array $rows): self
    {
        return new self(
            customers: array_map(
                fn (array $row) => [
                    'customerId' => $row['customer_id'],
                    'name' => $row['name'],
                    'totalOrders' => $row['total_orders'],
                    'totalSpent' => $row['total_spent'],
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
            'customers' => $this->customers,
        ];
    }
}
