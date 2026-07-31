<?php

namespace App\Modules\Reporting\Application\DTOs;

/**
 * Structured data transfer for a computed Sales Report. Built via
 * `fromArray()`, not `fromEntity()` — unlike every other DTO in this
 * codebase, there is no Domain Entity behind this data (SalesReportGenerator's
 * output is a pure computation, never persisted as its own aggregate;
 * `Report`/`ReportResult` are what actually get persisted — see
 * ReportResult's own docblock). camelCase keys, same convention every
 * other DTO in this codebase uses for its `toArray()` output.
 */
final class SalesReportData
{
    /**
     * @param array<string, int> $salesByDay
     */
    public function __construct(
        public readonly int $totalSales,
        public readonly int $totalOrders,
        public readonly int $averageOrderValue,
        public readonly array $salesByDay,
    ) {
    }

    /**
     * @param array{totalSales: int, totalOrders: int, averageOrderValue: int, salesByDay: array<string, int>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalSales: $data['totalSales'],
            totalOrders: $data['totalOrders'],
            averageOrderValue: $data['averageOrderValue'],
            salesByDay: $data['salesByDay'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalSales' => $this->totalSales,
            'totalOrders' => $this->totalOrders,
            'averageOrderValue' => $this->averageOrderValue,
            'salesByDay' => $this->salesByDay,
        ];
    }
}
