<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\MarketIntelligenceQuery;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Read model for Market Intelligence (Phase 8/M2) — same
 * "Controller/handler -> one Action -> Query class" shape
 * GetBusinessAnalyticsAction (Phase 8/M1) already established. Unlike that
 * one, this reaches beyond the caller's own numbers into an industry-wide
 * view of OTHER Businesses, so — like nexus.marketplace.search — it goes
 * through the CostGate rather than staying free.
 *
 * $industry defaults to the calling Business's own industry (the common
 * case: "how is my own market moving") but any industry may be requested —
 * this is aggregate, anonymized market data, not something specific to one
 * Business's relationships the way nexus.marketplace.network is.
 */
final class GetMarketIntelligenceAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly MarketIntelligenceQuery $market,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{
     *     industry: string,
     *     priceTrend: list<array>,
     *     demandSignal: list<array>,
     *     competitorStats: array
     * }
     */
    public function execute(int $callingBusinessId, ?string $industry = null, ?DateTimeInterface $from = null, ?DateTimeInterface $to = null): array
    {
        $business = $this->businesses->findById($callingBusinessId);

        if (! $business) {
            throw new InvalidArgumentException("Business [{$callingBusinessId}] does not exist.");
        }

        $targetIndustry = $industry ?? $business->industry()->value;
        $minSampleSize = (int) config('nexus.platform.analytics.min_market_intelligence_sample_size');

        $this->costGate->execute($callingBusinessId, 'nexus.analytics.market');

        return [
            'industry' => $targetIndustry,
            'priceTrend' => $this->market->priceTrend($targetIndustry, $from, $to),
            'demandSignal' => $this->market->demandSignal($targetIndustry, $from, $to),
            'competitorStats' => $this->market->competitorStats($targetIndustry, $callingBusinessId, $minSampleSize),
        ];
    }
}
