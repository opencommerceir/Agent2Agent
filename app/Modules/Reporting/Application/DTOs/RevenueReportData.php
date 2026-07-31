<?php

namespace App\Modules\Reporting\Application\DTOs;

/**
 * Structured data transfer for a computed Revenue Report. Built via
 * `fromArray()` — see SalesReportData's own docblock for why.
 */
final class RevenueReportData
{
    public function __construct(
        public readonly int $grossRevenue,
        public readonly int $taxCollected,
        public readonly int $discountsApplied,
        public readonly int $netRevenue,
    ) {
    }

    /**
     * @param array{grossRevenue: int, taxCollected: int, discountsApplied: int, netRevenue: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            grossRevenue: $data['grossRevenue'],
            taxCollected: $data['taxCollected'],
            discountsApplied: $data['discountsApplied'],
            netRevenue: $data['netRevenue'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'grossRevenue' => $this->grossRevenue,
            'taxCollected' => $this->taxCollected,
            'discountsApplied' => $this->discountsApplied,
            'netRevenue' => $this->netRevenue,
        ];
    }
}
