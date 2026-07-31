<?php

namespace App\Modules\Reporting\Application\DTOs;

/**
 * Structured data transfer for a computed Loyalty Report. Built via
 * `fromArray()` — see SalesReportData's own docblock for why.
 */
final class LoyaltyReportData
{
    /**
     * @param list<array{customerId: int, name: string, pointsEarned: int}> $topEarners
     */
    public function __construct(
        public readonly int $totalPointsEarned,
        public readonly int $totalPointsRedeemed,
        public readonly int $activeAccounts,
        public readonly array $topEarners,
    ) {
    }

    /**
     * @param array{
     *     totalPointsEarned: int,
     *     totalPointsRedeemed: int,
     *     activeAccounts: int,
     *     topEarners: list<array{customer_id: int, name: string, points_earned: int}>
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            totalPointsEarned: $data['totalPointsEarned'],
            totalPointsRedeemed: $data['totalPointsRedeemed'],
            activeAccounts: $data['activeAccounts'],
            topEarners: array_map(
                fn (array $row) => [
                    'customerId' => $row['customer_id'],
                    'name' => $row['name'],
                    'pointsEarned' => $row['points_earned'],
                ],
                $data['topEarners'],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totalPointsEarned' => $this->totalPointsEarned,
            'totalPointsRedeemed' => $this->totalPointsRedeemed,
            'activeAccounts' => $this->activeAccounts,
            'topEarners' => $this->topEarners,
        ];
    }
}
