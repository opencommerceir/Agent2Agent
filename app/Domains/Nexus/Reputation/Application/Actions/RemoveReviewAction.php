<?php

namespace App\Domains\Nexus\Reputation\Application\Actions;

use App\Domains\Nexus\Reputation\Application\DTOs\ReviewData;
use App\Domains\Nexus\Reputation\Domain\Repositories\ReviewRepositoryInterface;
use InvalidArgumentException;

/**
 * Admin-only (Dashboard, core `auth`/`admin` guard, never `business.auth`)
 * — the flag was upheld, so the review is permanently removed (terminal
 * state, ReviewStatus/Review entity's own ALLOWED_TRANSITIONS never lets
 * Removed transition anywhere else).
 */
final class RemoveReviewAction
{
    public function __construct(
        private readonly ReviewRepositoryInterface $reviews,
    ) {
    }

    public function execute(int $reviewId): ReviewData
    {
        $review = $this->reviews->findById($reviewId);

        if (! $review) {
            throw new InvalidArgumentException("Review [{$reviewId}] does not exist.");
        }

        $review->remove();

        return ReviewData::fromEntity($this->reviews->save($review));
    }
}
