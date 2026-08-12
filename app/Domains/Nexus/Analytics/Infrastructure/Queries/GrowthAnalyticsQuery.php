<?php

namespace App\Domains\Nexus\Analytics\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Growth\Infrastructure\Models\Invite;
use App\Domains\Nexus\Growth\Infrastructure\Models\ReferralSignup;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Growth (Invite/ReferralSignup) + Business for the Viral Analytics
 * dashboard (Phase 5/M5). Same "Infrastructure\Queries\*, not a bloated
 * Repository" convention RevenueQuery/BusinessSearchQuery/NetworkQuery
 * already established.
 *
 * Cohorts are grouped by registration week (`businesses.created_at`), not
 * "verification week" — the `businesses` table has no `verified_at`
 * column (VerifyBusinessAction only flips `verification_status`), so
 * registration week is the honest signal actually available, not an
 * invented one.
 */
class GrowthAnalyticsQuery
{
    /**
     * @return array{sent: int, converted: int, invitingBusinesses: int}
     */
    public function inviteTotals(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $query = Invite::query();
        $this->applyRange($query, $from, $to, 'created_at');

        return [
            'sent' => (clone $query)->count(),
            'converted' => (clone $query)->where('status', 'converted')->count(),
            // pluck+unique rather than distinct()->count() — avoids the
            // documented cross-driver ambiguity Phase 4/M7 already flagged
            // for aggregate-plus-distinct combos.
            'invitingBusinesses' => (clone $query)->pluck('inviter_business_id')->unique()->count(),
        ];
    }

    /**
     * @return list<array{cohortWeek: string, businessesRegistered: int, referredCount: int, invitesSent: int, invitesConverted: int}>
     */
    public function cohorts(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $businessQuery = Business::query();
        $this->applyRange($businessQuery, $from, $to, 'created_at');

        $businesses = $businessQuery->get(['id', 'created_at']);
        $referredBusinessIds = ReferralSignup::query()->pluck('referee_business_id')->flip();

        $byWeek = [];

        foreach ($businesses as $business) {
            $week = $business->created_at->startOfWeek()->format('Y-m-d');
            $byWeek[$week] ??= ['businessesRegistered' => 0, 'referredCount' => 0, 'businessIds' => []];
            $byWeek[$week]['businessesRegistered']++;
            $byWeek[$week]['businessIds'][] = $business->id;

            if ($referredBusinessIds->has($business->id)) {
                $byWeek[$week]['referredCount']++;
            }
        }

        ksort($byWeek);

        return array_map(function (string $week) use ($byWeek) {
            $businessIds = $byWeek[$week]['businessIds'];

            return [
                'cohortWeek' => $week,
                'businessesRegistered' => $byWeek[$week]['businessesRegistered'],
                'referredCount' => $byWeek[$week]['referredCount'],
                'invitesSent' => Invite::query()->whereIn('inviter_business_id', $businessIds)->count(),
                'invitesConverted' => Invite::query()->whereIn('inviter_business_id', $businessIds)->where('status', 'converted')->count(),
            ];
        }, array_keys($byWeek));
    }

    /**
     * A/B testing (roadmap: "A/B testing برای پیام‌های دعوت") — conversion
     * rate per Invite::messageVariant.
     *
     * @return list<array{variant: string, sent: int, converted: int, conversionRate: float}>
     */
    public function inviteVariants(?DateTimeInterface $from, ?DateTimeInterface $to): array
    {
        $query = Invite::query();
        $this->applyRange($query, $from, $to, 'created_at');

        $rows = $query->selectRaw('message_variant, count(*) as sent, sum(case when status = ? then 1 else 0 end) as converted', ['converted'])
            ->groupBy('message_variant')
            ->orderBy('message_variant')
            ->get();

        return $rows->map(fn ($row) => [
            'variant' => $row->message_variant,
            'sent' => (int) $row->sent,
            'converted' => (int) $row->converted,
            'conversionRate' => $row->sent > 0 ? round($row->converted / $row->sent * 100, 1) : 0.0,
        ])->all();
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
