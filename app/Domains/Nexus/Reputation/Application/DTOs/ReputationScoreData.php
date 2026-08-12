<?php

namespace App\Domains\Nexus\Reputation\Application\DTOs;

/**
 * Structured data transfer for a computed reputation score.
 * Represents data only — no business logic (DTO Conventions).
 */
final class ReputationScoreData
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $score,
        public readonly float $successRate,
        public readonly float $averageRating,
        public readonly int $reviewCount,
        public readonly int $completedDeals,
        public readonly int $longevityMonths,
        /** @var list<string> */
        public readonly array $badges,
    ) {
    }

    /**
     * @return array{businessId: int, score: int, successRate: float, averageRating: float, reviewCount: int, completedDeals: int, longevityMonths: int, badges: list<string>}
     */
    public function toArray(): array
    {
        return [
            'businessId' => $this->businessId,
            'score' => $this->score,
            'successRate' => $this->successRate,
            'averageRating' => $this->averageRating,
            'reviewCount' => $this->reviewCount,
            'completedDeals' => $this->completedDeals,
            'longevityMonths' => $this->longevityMonths,
            'badges' => $this->badges,
        ];
    }
}
