<?php

namespace App\Modules\Reporting\Domain\Services;

/**
 * Pure, framework-free. `top_earners` merging follows the exact same
 * "SQL already ranked/limited it, this just attaches the display name"
 * shape TopProductsReportGenerator/TopCustomersReportGenerator have —
 * `LoyaltyQueryBuilder::topEarners()` already joins `loyalty_accounts`
 * to resolve `customer_id` in SQL, so the calling Action only needs one
 * further lookup per row, via Commerce's `CustomerRepositoryInterface`,
 * to resolve the display name.
 */
final class LoyaltyReportGenerator
{
    /**
     * @param list<array{loyalty_account_id: int, customer_id: int, points_earned: int}> $topEarnerRows
     * @param array<int, string> $customerNames customer_id => full name
     * @return array{
     *     totalPointsEarned: int,
     *     totalPointsRedeemed: int,
     *     activeAccounts: int,
     *     topEarners: list<array{customer_id: int, name: string, points_earned: int}>
     * }
     */
    public function generate(
        int $totalPointsEarned,
        int $totalPointsRedeemed,
        int $activeAccounts,
        array $topEarnerRows,
        array $customerNames,
    ): array {
        return [
            'totalPointsEarned' => $totalPointsEarned,
            'totalPointsRedeemed' => $totalPointsRedeemed,
            'activeAccounts' => $activeAccounts,
            'topEarners' => array_map(
                fn (array $row) => [
                    'customer_id' => $row['customer_id'],
                    'name' => $customerNames[$row['customer_id']] ?? "Customer #{$row['customer_id']}",
                    'points_earned' => $row['points_earned'],
                ],
                $topEarnerRows,
            ),
        ];
    }
}
