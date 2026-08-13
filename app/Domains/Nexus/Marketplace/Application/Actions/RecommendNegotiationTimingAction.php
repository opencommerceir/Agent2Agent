<?php

namespace App\Domains\Nexus\Marketplace\Application\Actions;

use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Marketplace\Infrastructure\Queries\NegotiationTimingQuery;
use InvalidArgumentException;

/**
 * "Optimal timing" (Phase 8/M3, roadmap: "زمان‌بندی بهینه") — which day of
 * the week a Negotiation targeting this specific counterparty has
 * historically been most likely to close Accepted. See
 * NegotiationTimingQuery's own docblock for why day-of-week acceptance
 * rate is the one honest timing signal available (not response latency).
 */
final class RecommendNegotiationTimingAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businessRepository,
        private readonly NegotiationTimingQuery $timing,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{counterpartyBusinessId: int, byDayOfWeek: list<array>, sampleSize: int}
     */
    public function execute(int $callingBusinessId, int $counterpartyBusinessId): array
    {
        if (! $this->businessRepository->findById($counterpartyBusinessId)) {
            throw new InvalidArgumentException("Business [{$counterpartyBusinessId}] does not exist.");
        }

        $this->costGate->execute($callingBusinessId, 'nexus.marketplace.negotiation_timing');

        $byDayOfWeek = $this->timing->acceptanceRateByDayOfWeek($counterpartyBusinessId);

        return [
            'counterpartyBusinessId' => $counterpartyBusinessId,
            'byDayOfWeek' => $byDayOfWeek,
            'sampleSize' => array_sum(array_column($byDayOfWeek, 'dealCount')),
        ];
    }
}
