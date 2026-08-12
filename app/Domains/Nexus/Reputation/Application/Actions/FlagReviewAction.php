<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Reputation\Application\DTOs\ReviewData;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use InvalidArgumentException;

/**
 * Only the Business a review is ABOUT may flag it for moderation — the
 * reviewer already said what they wanted to say; flagging exists so the
 * reviewee has a real recourse against an unfair/bad-faith review, not so
 * either side can suppress the other. Self-contained authorization, same
 * as every other Nexus Action.
 */
final class FlagReviewAction
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {
    }

    public function execute(int $reviewId, int $actingBusinessId): ReviewData
    {
        $review = $this->reviews->findById($reviewId);

        if (! $review) {
            throw new InvalidArgumentException("Review [{$reviewId}] does not exist.");
        }

        if ($review->revieweeBusinessId() !== $actingBusinessId) {
            throw new InvalidArgumentException("Business [{$actingBusinessId}] is not the subject of this Review.");
        }

        $review->flag();

        return ReviewData::fromEntity($this->reviews->save($review));
    }
}
