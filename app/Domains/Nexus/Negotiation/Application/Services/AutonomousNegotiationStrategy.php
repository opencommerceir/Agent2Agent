<?php

namespace App\Domains\Nexus\Negotiation\Application\Services;

use App\Domains\Nexus\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Domains\Nexus\Catalog\Domain\Repositories\ServiceRepositoryInterface;
use App\Domains\Nexus\Negotiation\Domain\Entities\Negotiation;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\Money;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\NegotiationTerms;

/**
 * Decides what an autonomous Agent does with an incoming proposal/counter
 * — accept, counter (at what price), or reject. `NegotiationReasoningService`
 * (existing) only *explains* terms someone else already decided; nothing
 * in this codebase computed a price before this class.
 *
 * Purely rule-based (no LLM call), matching CLAUDE.md's "80% Rule Engine"
 * default — the catalog item's own list price is the anchor. Whichever
 * side actually owns the item (Product/Service::businessId()) wants the
 * price to land *above* that anchor; the other side wants it *below* —
 * both bounded by the same `tolerancePercent` window around the list
 * price, read from the responding Agent's own `strategies['tolerance_percent']`.
 *
 * Every counter this produces moves strictly toward the responding
 * party's own boundary (never backward, never a repeat of the price
 * already on the table) — combined with Negotiation's own round-limit
 * guard, this always terminates in a bounded number of rounds, never an
 * infinite back-and-forth.
 */
final class AutonomousNegotiationStrategy
{
    private const FAR_OFF_MULTIPLIER = 2.0;

    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    /**
     * @return array{action: 'accept'|'counter'|'reject', terms: ?NegotiationTerms}
     */
    public function decide(Negotiation $negotiation, NegotiationTerms $incomingTerms, int $respondingBusinessId, float $tolerancePercent): array
    {
        $item = $this->findItem($negotiation);

        if ($item === null) {
            // The catalog item this Negotiation is about no longer exists —
            // nothing sane to compare a price against.
            return ['action' => 'reject', 'terms' => null];
        }

        $isSeller = $item['ownerBusinessId'] === $respondingBusinessId;
        $listPrice = $item['priceAmount'];
        $incomingPrice = $incomingTerms->price()->amount();

        $lowerBound = (int) round($listPrice * (1 - $tolerancePercent / 100));
        $upperBound = (int) round($listPrice * (1 + $tolerancePercent / 100));

        $acceptable = $isSeller ? $incomingPrice >= $lowerBound : $incomingPrice <= $upperBound;

        if ($acceptable) {
            return ['action' => 'accept', 'terms' => null];
        }

        $farOffThreshold = $isSeller
            ? $listPrice * (1 - self::FAR_OFF_MULTIPLIER * $tolerancePercent / 100)
            : $listPrice * (1 + self::FAR_OFF_MULTIPLIER * $tolerancePercent / 100);
        $wayOff = $isSeller ? $incomingPrice < $farOffThreshold : $incomingPrice > $farOffThreshold;

        if ($wayOff || $negotiation->roundCount() >= $negotiation->maxRounds()) {
            return ['action' => 'reject', 'terms' => null];
        }

        $myBoundary = $isSeller ? $lowerBound : $upperBound;
        $counterPrice = (int) round(($incomingPrice + $myBoundary) / 2);
        $counterPrice = $isSeller ? max($counterPrice, $lowerBound) : min($counterPrice, $upperBound);

        return [
            'action' => 'counter',
            'terms' => new NegotiationTerms(
                Money::fromAmount($counterPrice, $incomingTerms->price()->currency()),
                $incomingTerms->quantity(),
                'Auto-response',
            ),
        ];
    }

    /**
     * @return array{priceAmount: int, ownerBusinessId: int}|null
     */
    private function findItem(Negotiation $negotiation): ?array
    {
        $item = match ($negotiation->catalogItemType()) {
            CatalogItemType::Product => $this->products->findById($negotiation->catalogItemId()),
            CatalogItemType::Service => $this->services->findById($negotiation->catalogItemId()),
        };

        return $item ? ['priceAmount' => $item->price()->amount(), 'ownerBusinessId' => $item->businessId()] : null;
    }
}
