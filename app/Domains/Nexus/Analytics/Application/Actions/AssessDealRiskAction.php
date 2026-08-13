<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\PredictiveIntelligenceQuery;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Credit\Application\Actions\SpendCreditsForActionAction;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * "Risk assessment for deals" (Phase 8/M5, roadmap: "ریسک‌سنجی معاملات") —
 * a rule-based 0-100 risk score for proposing (or already having proposed)
 * a deal of a given size with a given counterparty. Three weighted,
 * explainable factors, each backed by a real signal already in this
 * codebase, same "Rule Engine, not ML" default the whole Reasoning/
 * Reputation stack already follows:
 *
 *   1. Reputation (0-50 pts): a low Reputation score raises risk.
 *   2. Recent disputes lost (0-30 pts): disputes an arbiter actually ruled
 *      against this counterparty in the recent window (not merely
 *      "involved in one" — same distinction ReputationQuery's own dispute
 *      penalty already draws).
 *   3. Deal-size anomaly (0-20 pts): a deal far larger than this
 *      counterparty's own historical average accepted deal size is
 *      inherently riskier — more to lose if something goes wrong, and less
 *      precedent that they reliably close deals at this scale.
 */
final class AssessDealRiskAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly PredictiveIntelligenceQuery $predictive,
        private readonly CalculateReputationScoreAction $calculateReputationScore,
        private readonly SpendCreditsForActionAction $costGate,
    ) {
    }

    /**
     * @return array{
     *     counterpartyBusinessId: int,
     *     riskScore: int,
     *     riskLevel: string,
     *     factors: array{reputationPoints: int, disputePoints: int, dealSizePoints: int},
     *     reputationScore: int,
     *     disputesLostRecent: int,
     *     dealSizeRatio: float|null
     * }
     */
    public function execute(int $callingBusinessId, int $counterpartyBusinessId, int $dealAmount, string $currency): array
    {
        if (! $this->businesses->findById($counterpartyBusinessId)) {
            throw new InvalidArgumentException("Business [{$counterpartyBusinessId}] does not exist.");
        }

        $this->costGate->execute($callingBusinessId, 'nexus.analytics.risk');

        $weights = config('nexus.platform.intelligence.risk_weights');
        $disputeWindowDays = (int) config('nexus.platform.intelligence.risk_dispute_window_days');

        $reputation = $this->calculateReputationScore->execute($counterpartyBusinessId);
        $disputesLostRecent = $this->predictive->disputesLostWithinDays($counterpartyBusinessId, new DateTimeImmutable(), $disputeWindowDays);
        $averageDealSizes = $this->predictive->averageDealSizeByCurrency($counterpartyBusinessId);
        $averageDealSize = $averageDealSizes[$currency] ?? null;
        $dealSizeRatio = $averageDealSize && $averageDealSize > 0 ? round($dealAmount / $averageDealSize, 2) : null;

        $reputationPoints = (int) round((1000 - $reputation->score) / 1000 * $weights['reputation_max_points']);
        $disputePoints = min($disputesLostRecent * $weights['points_per_recent_dispute_loss'], $weights['dispute_max_points']);
        $dealSizePoints = $this->dealSizePoints($dealSizeRatio, $weights);

        $riskScore = min(100, $reputationPoints + $disputePoints + $dealSizePoints);

        return [
            'counterpartyBusinessId' => $counterpartyBusinessId,
            'riskScore' => $riskScore,
            'riskLevel' => $this->riskLevel($riskScore),
            'factors' => [
                'reputationPoints' => $reputationPoints,
                'disputePoints' => $disputePoints,
                'dealSizePoints' => $dealSizePoints,
            ],
            'reputationScore' => $reputation->score,
            'disputesLostRecent' => $disputesLostRecent,
            'dealSizeRatio' => $dealSizeRatio,
        ];
    }

    private function dealSizePoints(?float $ratio, array $weights): int
    {
        if ($ratio === null) {
            return 0; // no history to compare against — nothing honest to penalize
        }

        if ($ratio > 3.0) {
            return $weights['deal_size_max_points'];
        }

        if ($ratio > 1.5) {
            return (int) round($weights['deal_size_max_points'] / 2);
        }

        return 0;
    }

    private function riskLevel(int $score): string
    {
        return match (true) {
            $score < 30 => 'low',
            $score < 60 => 'medium',
            default => 'high',
        };
    }
}
