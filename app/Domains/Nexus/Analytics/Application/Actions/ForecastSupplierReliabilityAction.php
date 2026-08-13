<?php

namespace App\Domains\Nexus\Analytics\Application\Actions;

use App\Domains\Nexus\Analytics\Infrastructure\Queries\PredictiveIntelligenceQuery;
use App\Domains\Nexus\Business\Domain\Repositories\BusinessRepositoryInterface;
use App\Domains\Nexus\Reputation\Application\Actions\CalculateReputationScoreAction;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * "Predict supplier reliability" (Phase 8/M5, roadmap: "پیش‌بینی اعتبار
 * تامین‌کننده"). Not a forecast in the statistical-model sense — a
 * trend read of two adjacent real windows of the Business's own recent
 * Negotiation outcomes (PredictiveIntelligenceQuery::successRateTrend()),
 * next to its current computed Reputation score (Phase 6/M2, reused
 * rather than recomputed). Reports 'insufficient_data' honestly whenever
 * either window has too few outcomes to compare rather than pretending a
 * trend exists on thin data — same honesty
 * GrowthAnalyticsQuery's cohort-by-registration-week substitution and
 * ReputationQuery's own refusal to fabricate a "response time" signal
 * already established.
 */
final class ForecastSupplierReliabilityAction
{
    public function __construct(
        private readonly BusinessRepositoryInterface $businesses,
        private readonly PredictiveIntelligenceQuery $predictive,
        private readonly CalculateReputationScoreAction $calculateReputationScore,
    ) {
    }

    /**
     * @return array{
     *     businessId: int,
     *     currentScore: int,
     *     badges: list<string>,
     *     trend: string,
     *     recentSuccessRate: float|null,
     *     priorSuccessRate: float|null,
     *     minSampleSize: int
     * }
     */
    public function execute(int $businessId): array
    {
        if (! $this->businesses->findById($businessId)) {
            throw new InvalidArgumentException("Business [{$businessId}] does not exist.");
        }

        $recentWindowDays = (int) config('nexus.platform.intelligence.trend_recent_window_days');
        $priorWindowDays = (int) config('nexus.platform.intelligence.trend_prior_window_days');
        $minSampleSize = (int) config('nexus.platform.intelligence.trend_min_sample_size');

        $reputation = $this->calculateReputationScore->execute($businessId);
        $trend = $this->predictive->successRateTrend($businessId, new DateTimeImmutable(), $recentWindowDays, $priorWindowDays);

        return [
            'businessId' => $businessId,
            'currentScore' => $reputation->score,
            'badges' => $reputation->badges,
            'trend' => $this->deriveTrend($trend, $minSampleSize),
            'recentSuccessRate' => $trend['recentRate'],
            'priorSuccessRate' => $trend['priorRate'],
            'minSampleSize' => $minSampleSize,
        ];
    }

    /**
     * @param  array{recentRate: float|null, recentCount: int, priorRate: float|null, priorCount: int}  $trend
     */
    private function deriveTrend(array $trend, int $minSampleSize): string
    {
        if ($trend['recentCount'] < $minSampleSize || $trend['priorCount'] < $minSampleSize) {
            return 'insufficient_data';
        }

        $delta = $trend['recentRate'] - $trend['priorRate'];

        if ($delta > 0.05) {
            return 'improving';
        }

        if ($delta < -0.05) {
            return 'declining';
        }

        return 'stable';
    }
}
