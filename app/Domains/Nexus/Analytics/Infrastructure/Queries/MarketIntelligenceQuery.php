<?php

namespace App\Domains\Nexus\Analytics\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Product;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Service;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation;
use DateTimeInterface;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Negotiation + Catalog + Business for the Market Intelligence
 * feature (Phase 8/M2, roadmap: "ترندهای قیمتی، پیش‌بینی تقاضا و تحلیل
 * رقبا (anonymized)"). Same "Infrastructure\Queries\*, not a bloated
 * Repository" convention BusinessAnalyticsQuery (Phase 8/M1) already
 * established one milestone earlier.
 *
 * "Industry" here always means the SELLER's (counterparty's) industry — a
 * price/demand trend for an industry is about what businesses IN that
 * industry sell, not who happens to be buying from them.
 *
 * **Anonymization is k-anonymity, the same mechanism
 * BusinessAnalyticsQuery's own price benchmark already applies, not a
 * separate scheme** — competitorStats() suppresses every aggregate
 * (returns null) whenever fewer than the configured minimum number of
 * distinct competing Businesses contribute to it, so a single named
 * competitor's numbers can never be reverse-engineered from a report of
 * "the industry."
 */
class MarketIntelligenceQuery
{
    /**
     * Average accepted negotiated unit price for deals targeting sellers in
     * $industry, grouped by calendar week — the closest honest signal this
     * codebase has to a "price trend" (no separate price-history table
     * exists; today's listed price is a snapshot, an accepted Negotiation's
     * `current_terms` is the only timestamped price series available).
     *
     * @return list<array{weekStart: string, currency: string, averageUnitAmount: int, dealCount: int}>
     */
    public function priceTrend(string $industry, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $sellerIds = $this->businessIdsInIndustry($industry);

        $negotiations = Negotiation::query()
            ->whereIn('counterparty_business_id', $sellerIds)
            ->where('status', 'accepted')
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get(['created_at', 'current_terms']);

        $byWeek = [];

        foreach ($negotiations as $negotiation) {
            $week = $negotiation->created_at->startOfWeek()->format('Y-m-d');
            $currency = $negotiation->current_terms['priceCurrency'];
            $unitAmount = (int) $negotiation->current_terms['priceAmount'];
            $key = "{$week}|{$currency}";

            $byWeek[$key] ??= ['weekStart' => $week, 'currency' => $currency, 'sum' => 0, 'count' => 0];
            $byWeek[$key]['sum'] += $unitAmount;
            $byWeek[$key]['count']++;
        }

        ksort($byWeek);

        return array_values(array_map(fn (array $row) => [
            'weekStart' => $row['weekStart'],
            'currency' => $row['currency'],
            'averageUnitAmount' => (int) round($row['sum'] / $row['count']),
            'dealCount' => $row['count'],
        ], $byWeek));
    }

    /**
     * How many Negotiations (any outcome — this measures buying INTENT,
     * not success) were opened against sellers in $industry, grouped by
     * calendar week.
     *
     * @return list<array{weekStart: string, proposalsCount: int}>
     */
    public function demandSignal(string $industry, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $sellerIds = $this->businessIdsInIndustry($industry);

        $negotiations = Negotiation::query()
            ->whereIn('counterparty_business_id', $sellerIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->get(['created_at']);

        $byWeek = [];

        foreach ($negotiations as $negotiation) {
            $week = $negotiation->created_at->startOfWeek()->format('Y-m-d');
            $byWeek[$week] = ($byWeek[$week] ?? 0) + 1;
        }

        ksort($byWeek);

        return array_map(fn (string $week) => ['weekStart' => $week, 'proposalsCount' => $byWeek[$week]], array_keys($byWeek));
    }

    /**
     * Anonymized aggregate competitor snapshot — never a per-business
     * breakdown, only industry-wide averages, and only once enough distinct
     * competing Businesses contribute (k-anonymity, see class docblock).
     *
     * @return array{
     *     competitorCount: int,
     *     averageProductPriceAmount: int|null,
     *     averageServicePriceAmount: int|null,
     *     currency: string,
     *     averageSuccessRatePercent: float|null
     * }
     */
    public function competitorStats(string $industry, int $excludingBusinessId, int $minSampleSize): array
    {
        $competitorIds = $this->businessIdsInIndustry($industry, $excludingBusinessId);
        $competitorCount = count($competitorIds);

        if ($competitorCount < $minSampleSize) {
            return [
                'competitorCount' => $competitorCount,
                'averageProductPriceAmount' => null,
                'averageServicePriceAmount' => null,
                'currency' => 'IRT',
                'averageSuccessRatePercent' => null,
            ];
        }

        $currency = Product::query()->whereIn('business_id', $competitorIds)->value('price_currency')
            ?? Service::query()->whereIn('business_id', $competitorIds)->value('price_currency')
            ?? 'IRT';

        $avgProduct = Product::query()->whereIn('business_id', $competitorIds)->where('price_currency', $currency)->avg('price_amount');
        $avgService = Service::query()->whereIn('business_id', $competitorIds)->where('price_currency', $currency)->avg('price_amount');

        $outcomeQuery = Negotiation::query()
            ->where(fn ($q) => $q->whereIn('initiator_business_id', $competitorIds)->orWhereIn('counterparty_business_id', $competitorIds))
            ->whereIn('status', ['accepted', 'rejected']);
        $totalOutcomes = (clone $outcomeQuery)->count();
        $acceptedOutcomes = (clone $outcomeQuery)->where('status', 'accepted')->count();

        return [
            'competitorCount' => $competitorCount,
            'averageProductPriceAmount' => $avgProduct !== null ? (int) round($avgProduct) : null,
            'averageServicePriceAmount' => $avgService !== null ? (int) round($avgService) : null,
            'currency' => $currency,
            'averageSuccessRatePercent' => $totalOutcomes > 0 ? round($acceptedOutcomes / $totalOutcomes * 100, 1) : null,
        ];
    }

    /**
     * @return list<int>
     */
    private function businessIdsInIndustry(string $industry, ?int $excludingBusinessId = null): array
    {
        return Business::query()
            ->where('verification_status', 'verified')
            ->where('industry', $industry)
            ->when($excludingBusinessId, fn ($q) => $q->where('id', '!=', $excludingBusinessId))
            ->pluck('id')
            ->all();
    }
}
