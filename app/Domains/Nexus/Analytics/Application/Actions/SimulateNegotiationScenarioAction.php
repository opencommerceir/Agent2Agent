<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\PredictiveIntelligenceQuery;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Negotiation\Domain\ValueObjects\CatalogItemType;
use InvalidArgumentException;

/**
 * "Scenario planning" (Phase 8/M5, roadmap: "Scenario planning") — given a
 * hypothetical unit price for a not-yet-proposed Negotiation, estimate the
 * likelihood a specific counterparty accepts it. Two real, honest inputs,
 * no ML:
 *
 *   1. acceptanceRateAsCounterparty — how often proposals TO this Business
 *      have historically been accepted at all (base rate).
 *   2. averageAcceptedUnitPriceAsSeller — what they've actually agreed to
 *      pay/accept for this catalog item type before (price baseline).
 *
 * The hypothetical price is compared against that baseline: at or below it
 * nudges the base rate up (capped at 100%), above it scales the base rate
 * down proportionally to how far above. Returns null (not a guessed 50%)
 * whenever either input has no real history — same honesty every other
 * "insufficient data" path in this codebase already follows.
 */
final class SimulateNegotiationScenarioAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly PredictiveIntelligenceQuery $predictive,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{
     *     counterpartyBusinessId: int,
     *     hypotheticalUnitAmount: int,
     *     baselineAverageUnitAmount: int|null,
     *     currency: string|null,
     *     baseAcceptanceRate: float|null,
     *     estimatedAcceptanceLikelihood: float|null
     * }
     */
    public function execute(int $callingBusinessId, int $counterpartyBusinessId, CatalogItemType $catalogItemType, int $hypotheticalUnitAmount): array
    {
        if (! $this->businesses->findById($counterpartyBusinessId)) {
            throw new InvalidArgumentException("Business [{$counterpartyBusinessId}] does not exist.");
        }

        $this->costGate->execute($callingBusinessId, 'nexus.analytics.scenario');

        $baseRate = $this->predictive->acceptanceRateAsCounterparty($counterpartyBusinessId);
        $baseline = $this->predictive->averageAcceptedUnitPriceAsSeller($counterpartyBusinessId, $catalogItemType->value);

        $likelihood = null;

        if ($baseRate !== null && $baseline !== null && $baseline['averageUnitAmount'] > 0) {
            $likelihood = $this->estimateLikelihood($baseRate, $hypotheticalUnitAmount, $baseline['averageUnitAmount']);
        }

        return [
            'counterpartyBusinessId' => $counterpartyBusinessId,
            'hypotheticalUnitAmount' => $hypotheticalUnitAmount,
            'baselineAverageUnitAmount' => $baseline['averageUnitAmount'] ?? null,
            'currency' => $baseline['currency'] ?? null,
            'baseAcceptanceRate' => $baseRate,
            'estimatedAcceptanceLikelihood' => $likelihood,
        ];
    }

    private function estimateLikelihood(float $baseRate, int $hypotheticalAmount, int $baselineAmount): float
    {
        if ($hypotheticalAmount <= $baselineAmount) {
            return min(1.0, round($baseRate * 1.2, 3));
        }

        $percentAbove = min(1.0, ($hypotheticalAmount - $baselineAmount) / $baselineAmount);

        return max(0.0, round($baseRate * (1 - $percentAbove), 3));
    }
}
