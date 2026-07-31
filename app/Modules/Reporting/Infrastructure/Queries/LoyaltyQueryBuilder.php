<?php

namespace App\Modules\Reporting\Infrastructure\Queries;

use App\Modules\Loyalty\Infrastructure\Models\LoyaltyAccount;
use App\Modules\Loyalty\Infrastructure\Models\PointTransaction;
use App\Modules\Reporting\Domain\ValueObjects\DateRange;

/**
 * See SalesQueryBuilder's own docblock for the full reasoning behind
 * this module querying another module's Eloquent Models directly — the
 * same exception applies here against Loyalty's tables, not just
 * Commerce's.
 *
 * `points` on `point_transactions` is a signed delta (PointTransaction
 * Entity's own docblock: positive for earn/bonus, negative for
 * redeem/expire) — `total_points_redeemed` negates the raw SUM back to a
 * positive "how many points were spent" figure for the report.
 * `active_accounts` is defined as "currently holds a positive balance"
 * (a snapshot, not date-range-scoped) rather than "had any transaction
 * in this date range" — a deliberate choice, since an account's overall
 * activity naturally spans beyond any one report's window; documented
 * here so a future report that wants the date-scoped definition instead
 * doesn't silently assume this one already means that.
 */
final class LoyaltyQueryBuilder
{
    private const TOP_EARNERS_LIMIT = 10;

    /**
     * @return array{total_points_earned: int, total_points_redeemed: int, active_accounts: int}
     */
    public function totals(int $tenantId, DateRange $dateRange): array
    {
        $earned = PointTransaction::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('transaction_type', ['earn', 'bonus'])
            ->whereBetween('created_at', [$dateRange->start(), $dateRange->end()])
            ->sum('points');

        $redeemed = PointTransaction::query()
            ->where('tenant_id', $tenantId)
            ->where('transaction_type', 'redeem')
            ->whereBetween('created_at', [$dateRange->start(), $dateRange->end()])
            ->sum('points');

        $activeAccounts = LoyaltyAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('current_balance', '>', 0)
            ->count();

        return [
            'total_points_earned' => (int) $earned,
            'total_points_redeemed' => (int) abs($redeemed),
            'active_accounts' => $activeAccounts,
        ];
    }

    /**
     * @return list<array{loyalty_account_id: int, customer_id: int, points_earned: int}>
     */
    public function topEarners(int $tenantId, DateRange $dateRange): array
    {
        $rows = PointTransaction::query()
            ->join('loyalty_accounts', 'loyalty_accounts.id', '=', 'point_transactions.loyalty_account_id')
            ->where('point_transactions.tenant_id', $tenantId)
            ->whereIn('point_transactions.transaction_type', ['earn', 'bonus'])
            ->whereBetween('point_transactions.created_at', [$dateRange->start(), $dateRange->end()])
            ->groupBy('point_transactions.loyalty_account_id', 'loyalty_accounts.customer_id')
            ->orderByDesc('points_earned')
            ->limit(self::TOP_EARNERS_LIMIT)
            ->selectRaw(
                'point_transactions.loyalty_account_id as loyalty_account_id, '
                .'loyalty_accounts.customer_id as customer_id, '
                .'SUM(point_transactions.points) as points_earned',
            )
            ->get();

        return $rows->map(fn ($row) => [
            'loyalty_account_id' => (int) $row->loyalty_account_id,
            'customer_id' => (int) $row->customer_id,
            'points_earned' => (int) $row->points_earned,
        ])->all();
    }
}
