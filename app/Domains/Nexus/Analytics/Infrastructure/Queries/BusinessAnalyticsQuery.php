<?php

namespace App\Domains\Nexus\Analytics\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Product;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Service;
use App\Domains\Nexus\Negotiation\Infrastructure\Models\Negotiation;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Negotiation + Catalog + Business for the business-portal Analytics
 * dashboard (Phase 8/M1, roadmap: "نرخ موفقیت معاملات، بنچمارک قیمت‌ها،
 * محاسبه‌گر صرفه‌جویی"). Same "Infrastructure\Queries\*, not a bloated
 * Repository" convention RevenueQuery/GrowthAnalyticsQuery/ReputationQuery
 * already establish.
 *
 * Deal success rate itself is NOT duplicated here — ReputationQuery::successRate()
 * (Phase 6/M2) already computes exactly the accepted/(accepted+rejected)
 * formula this dashboard needs, so GetBusinessAnalyticsAction injects that
 * Query directly rather than a second copy of the same math (Extend, Don't
 * Rebuild applies to Query classes too, not just Actions).
 *
 * **Price benchmark is deliberately industry-average, not item-level
 * matching.** There is no shared SKU/taxonomy across different Businesses'
 * catalogs (Catalog's own `attributes` JSON bag is free-form per Phase 1/M4),
 * so matching "this business's Widget" against "that business's Widget" by
 * name would be fragile and dishonest. Instead this compares the calling
 * Business's own average listed price per catalog item type against the
 * industry-wide average among OTHER verified Businesses in the same
 * industry — an aggregate, not a per-competitor, comparison. A benchmark
 * row is suppressed (industryAverageAmount = null) whenever fewer than
 * `nexus.platform.analytics.min_benchmark_sample_size` distinct competing
 * Businesses contribute a price in that currency, so a lone competitor's
 * exact price is never reverse-engineerable from a two-business average —
 * the same k-anonymity reasoning `MarketIntelligenceQuery` (Phase 8/M2)
 * applies to Market Intelligence.
 */
class BusinessAnalyticsQuery
{
    /**
     * @return array{accepted: int, rejected: int, expired: int, open: int}
     */
    public function dealOutcomeCounts(int $businessId): array
    {
        $party = fn ($q) => $q->where('initiator_business_id', $businessId)->orWhere('counterparty_business_id', $businessId);

        return [
            'accepted' => Negotiation::query()->where($party)->where('status', 'accepted')->count(),
            'rejected' => Negotiation::query()->where($party)->where('status', 'rejected')->count(),
            'expired' => Negotiation::query()->where($party)->where('status', 'expired')->count(),
            'open' => Negotiation::query()->where($party)->whereIn('status', ['proposed', 'countered', 'pending_approval'])->count(),
        ];
    }

    /**
     * Savings this Business earned by negotiating as the initiator (buyer)
     * on deals that closed Accepted — the listed catalog price at the
     * moment of comparison (today's price, the only one this codebase
     * keeps; no historical price-at-negotiation-time snapshot exists)
     * versus the price it actually agreed to pay. A negative figure is
     * shown honestly (it means the negotiated price ended up above today's
     * listing, not that the deal was a mistake — the listing may simply
     * have changed since), never floored at zero.
     *
     * @return array{totalsByCurrency: array<string, int>, dealCount: int, deals: list<array{negotiationId: int, catalogItemType: string, catalogItemId: int, listedUnitAmount: int, negotiatedUnitAmount: int, currency: string, quantity: int, savingsAmount: int}>}
     */
    public function savingsFromNegotiations(int $businessId): array
    {
        $negotiations = Negotiation::query()
            ->where('initiator_business_id', $businessId)
            ->where('status', 'accepted')
            ->get(['id', 'catalog_item_type', 'catalog_item_id', 'current_terms']);

        $deals = [];
        $totalsByCurrency = [];

        foreach ($negotiations as $negotiation) {
            $terms = $negotiation->current_terms;
            $listedUnitAmount = $this->listedUnitPrice($negotiation->catalog_item_type, $negotiation->catalog_item_id, $terms['priceCurrency']);

            if ($listedUnitAmount === null) {
                continue; // item deleted or currency mismatch since — nothing honest to compare against
            }

            $quantity = (int) $terms['quantity'];
            $savingsAmount = ($listedUnitAmount - (int) $terms['priceAmount']) * $quantity;

            $deals[] = [
                'negotiationId' => $negotiation->id,
                'catalogItemType' => $negotiation->catalog_item_type,
                'catalogItemId' => $negotiation->catalog_item_id,
                'listedUnitAmount' => $listedUnitAmount,
                'negotiatedUnitAmount' => (int) $terms['priceAmount'],
                'currency' => $terms['priceCurrency'],
                'quantity' => $quantity,
                'savingsAmount' => $savingsAmount,
            ];

            $totalsByCurrency[$terms['priceCurrency']] = ($totalsByCurrency[$terms['priceCurrency']] ?? 0) + $savingsAmount;
        }

        return ['totalsByCurrency' => $totalsByCurrency, 'dealCount' => count($deals), 'deals' => $deals];
    }

    /**
     * @return array{
     *     product: array{ownAverageAmount: int|null, ownCount: int, industryAverageAmount: int|null, industrySampleBusinessCount: int, currency: string},
     *     service: array{ownAverageAmount: int|null, ownCount: int, industryAverageAmount: int|null, industrySampleBusinessCount: int, currency: string}
     * }
     */
    public function industryPriceBenchmark(int $businessId, string $industry, int $minSampleSize): array
    {
        return [
            'product' => $this->benchmarkFor(Product::class, $businessId, $industry, $minSampleSize),
            'service' => $this->benchmarkFor(Service::class, $businessId, $industry, $minSampleSize),
        ];
    }

    private function benchmarkFor(string $modelClass, int $businessId, string $industry, int $minSampleSize): array
    {
        /** @var Product|Service $modelClass */
        $own = $modelClass::query()->where('business_id', $businessId)->get(['price_amount', 'price_currency']);
        $currency = $own->first()?->price_currency ?? 'IRT';

        $ownSameCurrency = $own->where('price_currency', $currency);

        $competitorBusinessIds = Business::query()
            ->where('verification_status', 'verified')
            ->where('industry', $industry)
            ->where('id', '!=', $businessId)
            ->pluck('id');

        $competitorItems = $modelClass::query()
            ->whereIn('business_id', $competitorBusinessIds)
            ->where('price_currency', $currency)
            ->get(['business_id', 'price_amount']);

        $sampleBusinessCount = $competitorItems->pluck('business_id')->unique()->count();

        return [
            'ownAverageAmount' => $ownSameCurrency->count() > 0 ? (int) round($ownSameCurrency->avg('price_amount')) : null,
            'ownCount' => $ownSameCurrency->count(),
            'industryAverageAmount' => $sampleBusinessCount >= $minSampleSize ? (int) round($competitorItems->avg('price_amount')) : null,
            'industrySampleBusinessCount' => $sampleBusinessCount,
            'currency' => $currency,
        ];
    }

    private function listedUnitPrice(string $catalogItemType, int $catalogItemId, string $expectedCurrency): ?int
    {
        $modelClass = $catalogItemType === 'product' ? Product::class : Service::class;
        $item = $modelClass::query()->find($catalogItemId);

        if (! $item || $item->price_currency !== $expectedCurrency) {
            return null;
        }

        return (int) $item->price_amount;
    }
}
