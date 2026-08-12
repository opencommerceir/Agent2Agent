<?php

namespace App\Domains\Nexus\Marketplace\Infrastructure\Queries;

use App\Domains\Nexus\Business\Infrastructure\Models\Business;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Product;
use App\Domains\Nexus\Catalog\Infrastructure\Models\Service;
use App\Domains\Nexus\Marketplace\Application\DTOs\MarketplaceListingData;
use Illuminate\Support\Collection;

/**
 * A plain, autowired concrete Query class — not a Repository — reading
 * across Business + Catalog for discovery purposes. Mirrors the base
 * platform's own Reporting module convention (Infrastructure\Queries\*
 * Query Builders, referenced by bootstrap/providers.php's own comment on
 * AnalyticsServiceProvider) rather than bloating BusinessRepositoryInterface/
 * ProductRepositoryInterface with marketplace-specific methods that don't
 * belong to those domains' own aggregates. Read-only, cross-domain by
 * design — Marketplace has no tables of its own.
 */
class BusinessSearchQuery
{
    /**
     * Verified businesses only, excluding the caller's own — a Business
     * cannot discover or negotiate with itself. Matches by business name
     * or by a catalog item's name when $query is given.
     *
     * @return list<MarketplaceListingData>
     */
    public function search(int $excludingBusinessId, ?string $query = null, ?string $industry = null): array
    {
        $businesses = Business::query()
            ->where('verification_status', 'verified')
            ->where('id', '!=', $excludingBusinessId)
            ->when($industry, fn ($q) => $q->where('industry', $industry))
            ->when($query, fn ($q) => $q->where(
                fn ($inner) => $inner
                    ->where('name_fa', 'like', "%{$query}%")
                    ->orWhere('name_en', 'like', "%{$query}%")
                    ->orWhereIn('id', $this->businessIdsWithMatchingCatalogItem($query))
            ))
            ->get();

        return $businesses
            ->map(fn (Business $business) => $this->toListing($business, $query))
            ->all();
    }

    /**
     * Same-industry verified businesses, excluding the caller — the
     * simplest honest "you might want to talk to these" heuristic
     * available before Reputation (Phase 6) exists to rank by anything
     * richer.
     *
     * @return list<MarketplaceListingData>
     */
    public function sameIndustry(int $excludingBusinessId, string $industry, int $limit = 5): array
    {
        $businesses = Business::query()
            ->where('verification_status', 'verified')
            ->where('id', '!=', $excludingBusinessId)
            ->where('industry', $industry)
            ->latest('id')
            ->limit($limit)
            ->get();

        return $businesses->map(fn (Business $business) => $this->toListing($business))->all();
    }

    /**
     * @param  list<int>  $businessIds
     * @return Collection<int, MarketplaceListingData>
     */
    public function listingsFor(array $businessIds): Collection
    {
        return Business::query()
            ->whereIn('id', $businessIds)
            ->get()
            ->map(fn (Business $business) => $this->toListing($business))
            ->keyBy(fn (MarketplaceListingData $listing) => $listing->businessId);
    }

    private function toListing(Business $business, ?string $query = null): MarketplaceListingData
    {
        $products = Product::query()
            ->where('business_id', $business->id)
            ->when($query, fn ($q) => $q->where(fn ($inner) => $inner->where('name_fa', 'like', "%{$query}%")->orWhere('name_en', 'like', "%{$query}%")))
            ->get(['id', 'name_fa', 'name_en', 'price_amount', 'price_currency', 'verification_status']);

        $services = Service::query()
            ->where('business_id', $business->id)
            ->when($query, fn ($q) => $q->where(fn ($inner) => $inner->where('name_fa', 'like', "%{$query}%")->orWhere('name_en', 'like', "%{$query}%")))
            ->get(['id', 'name_fa', 'name_en', 'price_amount', 'price_currency', 'verification_status']);

        return new MarketplaceListingData(
            businessId: $business->id,
            nameFa: $business->name_fa,
            nameEn: $business->name_en,
            industry: $business->industry,
            // 'verified' (Phase 6/M5) lets a calling Agent weigh a listing's
            // trust signal before ever proposing a Negotiation over it.
            products: $products->map(fn (Product $p) => [
                'id' => $p->id, 'nameFa' => $p->name_fa, 'nameEn' => $p->name_en,
                'priceAmount' => $p->price_amount, 'priceCurrency' => $p->price_currency,
                'verified' => $p->verification_status === 'verified',
            ])->all(),
            services: $services->map(fn (Service $s) => [
                'id' => $s->id, 'nameFa' => $s->name_fa, 'nameEn' => $s->name_en,
                'priceAmount' => $s->price_amount, 'priceCurrency' => $s->price_currency,
                'verified' => $s->verification_status === 'verified',
            ])->all(),
        );
    }

    /**
     * @return list<int>
     */
    private function businessIdsWithMatchingCatalogItem(string $query): array
    {
        $productBusinessIds = Product::query()
            ->where(fn ($q) => $q->where('name_fa', 'like', "%{$query}%")->orWhere('name_en', 'like', "%{$query}%"))
            ->pluck('business_id');

        $serviceBusinessIds = Service::query()
            ->where(fn ($q) => $q->where('name_fa', 'like', "%{$query}%")->orWhere('name_en', 'like', "%{$query}%"))
            ->pluck('business_id');

        return $productBusinessIds->merge($serviceBusinessIds)->unique()->values()->all();
    }
}
