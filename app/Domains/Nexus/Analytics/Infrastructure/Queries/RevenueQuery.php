<?php

namespace App\Domains\Nexus\Analytics\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Contract\Infrastructure\Models\Escrow;
use App\Domains\Nexus\Credit\Infrastructure\Models\CreditPurchaseSession;
use App\Domains\Nexus\Credit\Infrastructure\Models\CreditTransaction;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Credit + Contract + Business for the Revenue Dashboard (Phase
 * 3/M6). Same "Infrastructure\Queries\*, not a bloated Repository"
 * convention BusinessSearchQuery already established for Marketplace.
 *
 * Two real, distinct revenue streams (docs/claude/monetization.md):
 * **credit package sales** (real Toman a Business actually paid,
 * `nexus_credit_purchase_sessions.status = completed`) and **escrow
 * platform fees** (the 0.5%-of-deal-value cut, only counted once a deal's
 * Escrow reaches `released` — Held is pending/unearned, Disputed/Refunded
 * is reversed, matching real revenue-recognition intuition, not "money
 * the instant it's promised"). Credits *deducted* (Agent/negotiation
 * activity) is reported separately as a usage volume, not more revenue —
 * a Business already paid real money for those credits at purchase time.
 *
 * **Unit-scale normalization, deliberate and necessary:**
 * `nexus_credit_purchase_sessions.total_amount` is whole Toman (Credit's
 * own Money VO/CreditPackage — 500000 means 500,000 Toman, no minor unit,
 * matching how PurchaseCreditsAction already converts it ×10 to real IRR
 * for Zibal). `nexus_escrows.*_amount` inherits Negotiation's own Money
 * convention instead, which *does* store amounts in a 2-decimal minor
 * unit (Negotiation's show.blade.php already divides by 100 to display a
 * real Toman figure) — a pre-existing inconsistency between the two
 * domains' own Money VOs, not introduced here. Every escrow-derived money
 * figure below is divided by 100 before being combined with credit
 * package revenue, so every amount this class returns is already real,
 * whole Toman — callers (GetRevenueDashboardAction, the Blade view) never
 * need to know two different scales existed upstream.
 */
class RevenueQuery
{
    private const ESCROW_MINOR_UNIT_DIVISOR = 100;
    /**
     * @return array{amount: int, count: int}
     */
    public function creditPackageRevenue(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $query = CreditPurchaseSession::query()->where('status', 'completed');
        $this->applyRange($query, $from, $to, 'completed_at');

        return ['amount' => (int) $query->sum('total_amount'), 'count' => $query->count()];
    }

    /**
     * @return array{amount: int, count: int}
     */
    public function escrowFeeRevenue(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $query = Escrow::query()->where('status', 'released');
        $this->applyRange($query, $from, $to, 'released_at');

        return ['amount' => intdiv((int) $query->sum('platform_fee_amount'), self::ESCROW_MINOR_UNIT_DIVISOR), 'count' => $query->count()];
    }

    /**
     * @return array{grossAmount: int, count: int}
     */
    public function escrowPending(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $query = Escrow::query()->where('status', 'held');
        $this->applyRange($query, $from, $to, 'held_at');

        return ['grossAmount' => intdiv((int) $query->sum('gross_amount'), self::ESCROW_MINOR_UNIT_DIVISOR), 'count' => $query->count()];
    }

    public function creditsDeducted(?DateTimeInterface $from, ?DateTimeInterface $to): int
    {
        $query = CreditTransaction::query()->where('type', 'deduction');
        $this->applyRange($query, $from, $to, 'created_at');

        return (int) $query->sum('amount');
    }

    /**
     * @return list<array{businessId: int, nameFa: string, nameEn: string, industry: string, creditPackageRevenue: int, escrowFeeRevenue: int}>
     */
    public function perBusiness(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        return Business::query()
            ->get(['id', 'name_fa', 'name_en', 'industry'])
            ->map(function (Business $business) use ($from, $to) {
                $purchases = CreditPurchaseSession::query()->where('business_id', $business->id)->where('status', 'completed');
                $this->applyRange($purchases, $from, $to, 'completed_at');

                $fees = Escrow::query()
                    ->where('status', 'released')
                    ->where(fn ($q) => $q->where('business_a_id', $business->id)->orWhere('business_b_id', $business->id));
                $this->applyRange($fees, $from, $to, 'released_at');

                return [
                    'businessId' => $business->id,
                    'nameFa' => $business->name_fa,
                    'nameEn' => $business->name_en,
                    'industry' => $business->industry,
                    'creditPackageRevenue' => (int) $purchases->sum('total_amount'),
                    'escrowFeeRevenue' => intdiv((int) $fees->sum('platform_fee_amount'), self::ESCROW_MINOR_UNIT_DIVISOR),
                ];
            })
            ->filter(fn (array $row) => $row['creditPackageRevenue'] > 0 || $row['escrowFeeRevenue'] > 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array{industry: string, creditPackageRevenue: int, escrowFeeRevenue: int}>
     */
    public function perIndustry(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $rows = $this->perBusiness($from, $to);
        $byIndustry = [];

        foreach ($rows as $row) {
            $byIndustry[$row['industry']] ??= ['industry' => $row['industry'], 'creditPackageRevenue' => 0, 'escrowFeeRevenue' => 0];
            $byIndustry[$row['industry']]['creditPackageRevenue'] += $row['creditPackageRevenue'];
            $byIndustry[$row['industry']]['escrowFeeRevenue'] += $row['escrowFeeRevenue'];
        }

        return array_values($byIndustry);
    }

    /**
     * Credit package revenue only, grouped by calendar day — the escrow
     * fee side isn't included here (its own timestamp, `released_at`, is
     * a business-triggered event, not a steady daily platform-revenue
     * signal the way a purchase date is).
     *
     * @return list<array{date: string, amount: int}>
     */
    public function perDay(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $query = CreditPurchaseSession::query()->where('status', 'completed');
        $this->applyRange($query, $from, $to, 'completed_at');

        return $query
            ->selectRaw('DATE(completed_at) as date, SUM(total_amount) as amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->date, 'amount' => (int) $row->amount])
            ->all();
    }

    private function applyRange(Builder $query, ?DateTimeInterface $from, ?DateTimeInterface $to, string $column): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }
}
